<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Interfaces;

use RevisionTen\CMS\Model\PageStreamRead;
use Solarium\Core\Query\Helper;

interface SolrSerializerInterface
{
    /**
     * @param PageStreamRead $pageStreamRead
     * @param array          $payload
     * @param Helper         $helper
     *
     * @return array
     */
    public function serialize(PageStreamRead $pageStreamRead, array $payload, Helper $helper): array;
}
