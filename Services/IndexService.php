<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RevisionTen\CMS\Interfaces\SolrSerializerInterface;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use RevisionTen\CMS\Serializer\PageSerializer;
use Solarium\Client;
use Solarium\Core\Query\Helper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class IndexService
{
    /** @var ContainerInterface */
    private $container;

    /** @var EntityManagerInterface */
    protected $entityManager;

    /** @var LoggerInterface */
    protected $logger;

    /** @var PageService */
    protected $pageService;

    /** @var array */
    protected $config;

    /** @var array */
    protected $solrConfig;

    /**
     * IndexService constructor.
     *
     * @param ContainerInterface     $container
     * @param LoggerInterface        $logger
     * @param EntityManagerInterface $entityManager
     * @param PageService            $pageService
     * @param array                  $config
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger, EntityManagerInterface $entityManager, PageService $pageService, array $config)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->pageService = $pageService;
        $this->config = $config;

        $this->solrConfig = $config['solr_collection'] ? [
            'endpoint' => [
                'localhost' => [
                    'host' => 'localhost',
                    'port' => $config['solr_port'],
                    'path' => '/',
                    'collection' => $config['solr_collection'],
                ]
            ]
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

        $client = new Client($this->solrConfig);

        $update = $client->createUpdate();
        $update->addDeleteQuery('*:*');
        $update->addCommit();

        try {
            $result = $client->update($update);
        } catch (\Exception $exception) {
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

        $client = new Client($this->solrConfig);

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
            /** @var PageRead $page */
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
        } catch (\Exception $exception) {
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

    public static function reducePayload(array $payload, Helper $helper): array
    {
        $fulltext = [];

        $fulltext[] = $helper->filterControlCharacters($payload['title']);
        $fulltext[] = $helper->filterControlCharacters($payload['description']);

        if (isset($payload['meta']) && is_array($payload['meta']) && !empty($payload['meta'])) {
            $meta = self::reduceData($payload['meta']);
            // Append strings.
            foreach ($meta as $string) {
                $fulltext[] = $helper->filterControlCharacters($string);
            }
        }

        if (isset($payload['elements']) && is_array($payload['elements']) && !empty($payload['elements'])) {
            $strings = self::reduceData($payload['elements']);
            // Append strings.
            foreach ($strings as $string) {
                $fulltext[] = $helper->filterControlCharacters($string);
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
