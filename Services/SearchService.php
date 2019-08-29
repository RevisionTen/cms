<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Exception;
use Psr\Log\LoggerInterface;
use Solarium\Client;
use Solarium\QueryType\Select\Query\FilterQuery;
use Solarium\QueryType\Select\Query\Query;
use function addcslashes;
use function array_map;
use function ceil;
use function explode;
use function implode;
use function str_replace;

class SearchService
{
    /** @var array */
    protected $solrConfig;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger, array $config)
    {
        $this->logger = $logger;

        $this->solrConfig = [
            'endpoint' => [
                'localhost' => [
                    'host' => 'localhost',
                    'port' => $config['solr_port'],
                    'path' => '/',
                    'collection' => $config['solr_collection'],
                ]
            ]
        ];
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

        $client = new Client($this->solrConfig);
        $query = $client->createSelect();
        $query->setStart($start);
        $query->setRows($rows);

        self::getFulltextFilterQuery($query, $queryString);

        // Get search results.
        try {
            $resultset = $client->select($query);
        } catch (Exception $exception) {

            $this->logger->critical($exception->getMessage(), [
                'code' => $exception->getCode(),
            ]);

            return [];
        }

        // Get documents.
        $pages = [];
        /** @var \Solarium\QueryType\Select\Result\Document $document */
        foreach ($resultset->getDocuments() as $document) {
            $fields = (array) $document->getFields();
            $pages[$fields['id']] = $fields;
        }

        return [
            'pages' => $pages,
            'numFound' => $resultset->getNumFound(),
            'numPages' => ceil($resultset->getNumFound() / $rows),
            'page' => $page,
        ];
    }
}
