<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RevisionTen\CMS\Interfaces\SolrSerializerInterface;
use RevisionTen\CMS\Model\PageRead;
use RevisionTen\CMS\Model\PageStreamRead;
use Solarium\Client;
use Solarium\Core\Query\Helper;
use Symfony\Component\Console\Output\OutputInterface;

class IndexService
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var PageService */
    private $pageService;

    /** @var array */
    private $config;

    /** @var array */
    private $solrConfig;

    /**
     * IndexService constructor.
     *
     * @param LoggerInterface        $logger
     * @param EntityManagerInterface $entityManager
     * @param PageService            $pageService
     * @param array                  $config
     */
    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager, PageService $pageService, array $config)
    {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->pageService = $pageService;
        $this->config = $config;

        $this->solrConfig = [
            'endpoint' => [
                'localhost' => [
                    'host' => 'localhost',
                    'port' => $config['solr_port'],
                    'path' => '/solr/'.$config['solr_collection'].'/',
                ]
            ]
        ];
    }

    private function logError(OutputInterface &$output, string $title, string $message, int $code = 500): void
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

    public function index(OutputInterface &$output): void
    {
        $client = new Client($this->solrConfig);

        /** @var PageRead[] $pageReads */
        $pageReads = $this->entityManager->getRepository(PageRead::class)->findAll();
        $pageReadsByUuid = [];
        array_walk($pageReads, function ($page, $key) use(&$pageReadsByUuid) {
            /** @var PageRead $page */
            $pageReadsByUuid[$page->getUuid()] = $page;
        });

        /** @var PageStreamRead[] $pages */
        $pages = $this->entityManager->getRepository(PageStreamRead::class)->findAll();

        $update = $client->createUpdate();
        $helper = $update->getHelper();
        $docs = [];

        foreach ($pages as $page) {
            // Create solr document.
            $id = $page->getUuid();

            /** @var PageRead $pageRead */
            $pageRead = $pageReadsByUuid[$id] ?? null;

            // Get path of first alias attached to the pageStreamRead entity.
            $path = null;
            $aliases = $page->getAliases();
            if (null !== $aliases && !empty($aliases)) {
                if ($aliases instanceof Collection) {
                    $aliases = $aliases->toArray();
                }
                if (is_array($aliases) && !empty($aliases) && null !== array_values($aliases)[0]) {
                    $path = array_values($aliases)[0]->getPath();
                }
            }

            // Don't index if no alias path exists or page is deleted or unpublished.
            if (null === $path || $page->getDeleted() || $page->isPublished() === false) {
                // Delete page from index.
                $update->addDeleteById($id);
                $output->writeln('<info>Marked page '.$page->getTitle().' as deleted.</info>');
                continue;
            }

            $docs[$id] = $update->createDocument();
            $docs[$id]->id = $id;
            $docs[$id]->ispage_b = true;
            $docs[$id]->url_s = $path;
            $docs[$id]->title_s = $helper->filterControlCharacters($page->getTitle());
            $docs[$id]->website_i = $page->getWebsite();
            $docs[$id]->language_s = $page->getLanguage();
            $docs[$id]->template_s = $helper->filterControlCharacters($page->getTemplate());
            $docs[$id]->created_dt = $helper->formatDate($page->getCreated());
            $docs[$id]->modified_dt = $helper->formatDate($page->getModified());

            $payload = $pageRead->getPayload();
            $payload = $this->pageService->hydratePage($payload);
            $docs[$id]->fulltext = self::reducePayload($payload, $helper);

            // Add extra data provided by the pages serializer.
            /** @var SolrSerializerInterface $serializer */
            $serializer = $this->config['page_templates'][$page->getTemplate()]['solr_serializer'] ?? false;
            if ($serializer && class_exists($serializer) && in_array(SolrSerializerInterface::class, class_implements($serializer), true)) {
                $serializer = new $serializer();
                $extraFields = $serializer->serialize($page, $payload, $helper);
                foreach ($extraFields as $field => $value) {
                    $docs[$id]->{$field} = $value;
                }
            }
        }

        // Commit solr documents.
        $update->addDocuments($docs);
        $update->addCommit();

        try {
            $result = $client->update($update);
        } catch (\Exception $exception) {
            $this->logError($output, 'Index error', $exception->getMessage(), $exception->getCode());
        }

        if (isset($result) && $result && $result->getResponse()->getStatusCode() === 200) {
            $output->writeln('<info>Pushed '.count($pages).' documents: '.$result->getResponse()->getStatusMessage().'</info>');
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

        array_walk_recursive($data, function ($item, $fieldName) use (&$reducedData, $fieldNames) {
            if (is_string($fieldName)) {
                if ('doctrineEntity' === $fieldName && is_object($item)) {
                    // Serialize hydrated doctrine entities.
                    if (method_exists($item, 'serialize')) {
                        foreach ($item->serialize() as $value) {
                            $reducedData[] = html_entity_decode(strip_tags((string) $value));
                        }
                    }
                } elseif (is_string($item) && !empty($item) && in_array($fieldName, $fieldNames, true)) {
                    $reducedData[] = html_entity_decode(strip_tags($item));
                }
            }
        });

        return $reducedData;
    }
}