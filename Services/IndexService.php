<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use RevisionTen\CMS\Interfaces\SolrSerializerInterface;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Serializer\PageSerializer;
use Solarium\Core\Client\Adapter\Curl;
use Solarium\Core\Client\Client;
use Solarium\Core\Query\Helper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function array_push;
use function array_values;
use function array_walk;
use function array_walk_recursive;
use function class_exists;
use function class_implements;
use function count;
use function html_entity_decode;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function method_exists;
use function strip_tags;

class IndexService
{
    private ContainerInterface $container;

    protected EntityManagerInterface $entityManager;

    protected LoggerInterface $logger;

    protected PageService $pageService;

    protected array $config;

    protected ?array $solrConfig = null;

    public function __construct(ContainerInterface $container, LoggerInterface $logger, EntityManagerInterface $entityManager, PageService $pageService, array $config)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->pageService = $pageService;
        $this->config = $config;

        $endpoint = [
            'host' => $config['solr_host'],
            'port' => $config['solr_port'],
            'path' => '/',
            'collection' => $config['solr_collection'],
        ];

        if (!empty($config['solr_username']) && !empty($config['solr_password'])) {
            $endpoint['username'] = $config['solr_username'];
            $endpoint['password'] = $config['solr_password'];
        }

        $this->solrConfig = $config['solr_collection'] ? [
            'endpoint' => [
                'localhost' => $endpoint,
            ],
        ] : null;
    }

    private function logError(OutputInterface $output, string $title, string $message, int $code = 500): void
    {
        $this->logger->critical($title, [
            'message' => $message,
            'code' => $code,
        ]);
        $output->writeln('');
        $output->writeln('<error>'.$title.'</error>');
        $output->writeln('<error>'.$message.'</error>');
        $output->writeln('');
    }

    public function clear(OutputInterface $output): void
    {
        if (null === $this->solrConfig) {
            // Do nothing if solr is not configured.
            return;
        }

        $adapter = new Curl();
        $eventDispatcher = new EventDispatcher();
        $client = new Client($adapter, $eventDispatcher, $this->solrConfig);

        $update = $client->createUpdate();
        $update->addDeleteQuery('*:*');
        $update->addCommit();

        try {
            $result = $client->update($update);
        } catch (Exception $exception) {
            $this->logError($output, 'Index clear error', $exception->getMessage(), $exception->getCode());
        }

        if (isset($result) && $result && $result->getResponse()->getStatusCode() === 200) {
            $output->writeln('<info>Cleared index!</info>');
        } else {
            $errorMessage = isset($result) ? $result->getResponse()->getStatusMessage() : 'No solr result';
            $errorCode = isset($result) ? $result->getResponse()->getStatusCode() : 500;
            $this->logError($output, 'Index clear error', $errorMessage, $errorCode);
        }
    }

    public function index(OutputInterface $output, string $uuid = null): void
    {
        if (null === $this->solrConfig) {
            // Do nothing if solr is not configured.
            return;
        }

        $adapter = new Curl();
        $eventDispatcher = new EventDispatcher();
        $client = new Client($adapter, $eventDispatcher, $this->solrConfig);

        if (null === $uuid) {
            /** @var PageRead[] $pageReads */
            $pageReads = $this->entityManager->getRepository(PageRead::class)->findAll();
        } else {
            /** @var PageRead[] $pageReads */
            $pageReads = $this->entityManager->getRepository(PageRead::class)->findBy([
                'uuid' => $uuid,
            ]);
        }
        $pageReadsByUuid = [];
        array_walk($pageReads, static function ($page, $key) use(&$pageReadsByUuid) {
            /**
             * @var PageRead $page
             */
            $pageReadsByUuid[$page->getUuid()] = $page;
        });

        if (null === $uuid) {
            /** @var PageStreamRead[] $pages */
            $pages = $this->entityManager->getRepository(PageStreamRead::class)->findAll();
        } else {
            /** @var PageStreamRead[] $pages */
            $pages = $this->entityManager->getRepository(PageStreamRead::class)->findBy([
                'uuid' => $uuid,
            ]);
        }

        $update = $client->createUpdate();
        $allDocuments = [];

        foreach ($pages as $page) {
            /** @var PageRead $pageRead */
            $pageRead = $pageReadsByUuid[$page->getUuid()] ?? null;
            if ($pageRead) {
                $payload = $pageRead->getPayload();
                $payload = $this->pageService->hydratePage($payload);
            } else {
                $payload = null;
            }

            // Add documents provided by the pages serializer.
            $serializer = $this->config['page_templates'][$page->getTemplate()]['solr_serializer'] ?? false;
            if ($serializer && class_exists($serializer) && in_array(SolrSerializerInterface::class, class_implements($serializer), true)) {
                try {
                    // Get the serializer as a service.
                    $serializer = $this->container->get($serializer);
                } catch (ServiceNotFoundException $e) {
                    /** @var SolrSerializerInterface $serializer */
                    $serializer = new $serializer();
                }
            } else {
                $serializer = new PageSerializer();
            }
            $documents = $serializer->serialize($update, $page, $payload);
            if (!empty($documents)) {
                array_push($allDocuments, ...array_values($documents));
            }
        }

        // Commit solr documents.
        $update->addDocuments($allDocuments);
        $update->addCommit();
        $update->addOptimize();

        try {
            $result = $client->update($update);
        } catch (Exception $exception) {
            $this->logError($output, 'Index error', $exception->getMessage(), $exception->getCode());
        }

        if (isset($result) && $result && $result->getResponse()->getStatusCode() === 200) {
            $output->writeln('<info>Indexed '.count($pages).' pages: '.$result->getResponse()->getStatusMessage().'</info>');
            $output->writeln('<info>('.count($allDocuments).' documents)</info>');
        } else {
            $errorMessage = isset($result) ? $result->getResponse()->getStatusMessage() : 'No solr result';
            $errorCode = isset($result) ? $result->getResponse()->getStatusCode() : 500;
            $this->logError($output, 'Index error', $errorMessage, $errorCode);
        }
    }

    public static function removeInactive(array $elements, int $now): array
    {
        $elements = array_filter($elements, static function (array $element) use ($now) {
            $start = $element['data']['startDate'] ?? 0;
            $end = $element['data']['endDate'] ?? 999999999999;
            if (($now >= $start) && ($now <= $end)) {
                return true;
            } else {
                return false;
            }
        });

        foreach ($elements as &$element) {
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = self::removeInactive($element['elements'], $now);
            }
        }

        return $elements;
    }

    public static function reducePayload(array $payload, Helper $helper): array
    {
        $fulltext = [];

        if (!empty($payload['title']) && is_string($payload['title'])) {
            $fulltext[] = $helper->filterControlCharacters($payload['title']);
        }
        if (!empty($payload['description']) && is_string($payload['description'])) {
            $fulltext[] = $helper->filterControlCharacters($payload['description']);
        }

        if (isset($payload['meta']) && is_array($payload['meta']) && !empty($payload['meta'])) {
            $meta = self::reduceData($payload['meta']);
            // Append strings.
            foreach ($meta as $string) {
                if (is_string($string)) {
                    $fulltext[] = $helper->filterControlCharacters($string);
                }
            }
        }

        if (isset($payload['elements']) && is_array($payload['elements']) && !empty($payload['elements'])) {
            $nowDate = new \DateTime();
            $now = $nowDate->getTimestamp();
            $filteredElements = self::removeInactive($payload['elements'], $now);
            $strings = self::reduceData($filteredElements);
            // Append strings.
            foreach ($strings as $string) {
                if (is_string($string)) {
                    $fulltext[] = $helper->filterControlCharacters($string);
                }
            }
        }

        return $fulltext;
    }

    private static function reduceData(array $data): array
    {
        $reducedData = [];
        $fieldNames = [
            'title',
            'text',
            'buttonText',
            'brand',
            'locality',
            'email',
            'name',
            'telephone',
            'addressLocality',
            'addressRegion',
            'postalCode',
            'streetAddress',
        ];

        array_walk_recursive($data, static function ($item, $fieldName) use (&$reducedData, $fieldNames) {
            if (is_string($fieldName)) {
                if ('doctrineEntity' === $fieldName && is_object($item)) {
                    // Serialize hydrated doctrine entities.
                    if (method_exists($item, 'serializeToSolrArray')) {
                        $solrArray = $item->serializeToSolrArray();
                        if ($solrArray && is_array($solrArray)) {
                            foreach ($solrArray as $value) {
                                $reducedData[] = html_entity_decode(strip_tags((string) $value));
                            }
                        }
                    } elseif (method_exists($item, '__toString')) {
                        $reducedData[] = html_entity_decode((string) $item);
                    }
                } elseif (is_string($item) && !empty($item) && in_array($fieldName, $fieldNames, true)) {
                    $reducedData[] = html_entity_decode(strip_tags($item));
                }
            }
        });

        return $reducedData;
    }
}
