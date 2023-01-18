<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Exception;
use Psr\Log\LoggerInterface;
use Solarium\Core\Client\Adapter\Curl;
use Solarium\Core\Client\Client;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;
use Solarium\QueryType\Select\Result\Document;
use Symfony\Component\EventDispatcher\EventDispatcher;
use function addcslashes;
use function array_map;
use function ceil;
use function explode;
use function implode;
use function str_replace;

class SearchService
{
    protected ?array $solrConfig = null;

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, array $config)
    {
        $this->logger = $logger;

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

    public static function getFulltextFilterQuery(Query $query, string $queryString): FilterQuery
    {
        // Escape special characters in search string.
        $queryString = addcslashes($queryString, '+-&&||!(){}[]^"~*?:\\');
        $queryString = str_replace(' ', '+', $queryString);

        $filterQuery = $query->createFilterQuery('fulltext');

        // Split words.
        $queryStrings = explode('+', $queryString);
        $queryStrings = array_map(static function ($query) {
            return '(fulltext:*'.$query.'* OR fulltext:"'.$query.'")';
        }, $queryStrings);
        $fullQueryString = implode(' AND ', $queryStrings);

        $filterQuery->setQuery($fullQueryString);

        return $filterQuery;
    }

    public function getFulltextResults(string $queryString): array
    {
        $page = 0;
        $rows = 100;
        $start = $page * $rows;

        $adapter = new Curl();
        $eventDispatcher = new EventDispatcher();
        $client = new Client($adapter, $eventDispatcher, $this->solrConfig);
        $query = $client->createSelect();
        $query->setStart($start);
        $query->setRows($rows);

        self::getFulltextFilterQuery($query, $queryString);

        // Get search results.
        try {
            $resultSet = $client->select($query);
        } catch (Exception $exception) {

            $this->logger->critical($exception->getMessage(), [
                'code' => $exception->getCode(),
            ]);

            return [];
        }

        // Get documents.
        $pages = [];
        /**
         * @var Document $document
         */
        foreach ($resultSet->getDocuments() as $document) {
            $fields = $document->getFields();
            $pages[$fields['id']] = $fields;
        }

        return [
            'pages' => $pages,
            'numFound' => $resultSet->getNumFound(),
            'numPages' => ceil($resultSet->getNumFound() / $rows),
            'page' => $page,
        ];
    }
}
