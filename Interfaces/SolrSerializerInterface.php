<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Interfaces;

use RevisionTen\CMS\Model\PageStreamRead;
use Solarium\QueryType\Update\Query\Query;

interface SolrSerializerInterface
{
    /**
     * @param Query           $update
     * @param PageStreamRead  $pageStreamRead
     * @param array|NULL      $payload
     *
     * @return array
     */
    public function serialize(Query $update, PageStreamRead $pageStreamRead, array $payload = null): array;
}
