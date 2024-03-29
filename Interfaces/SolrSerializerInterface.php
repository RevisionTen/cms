<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Interfaces;

use RevisionTen\CMS\Model\PageStreamRead;
use Solarium\QueryType\Update\Query\Query;

interface SolrSerializerInterface
{
    public function serialize(Query $update, PageStreamRead $pageStreamRead, array $payload = null): array;
}
