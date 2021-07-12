<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Interfaces;

use RevisionTen\CMS\Entity\PageStreamRead;

interface AliasSuggesterInterface
{
    public function suggest(PageStreamRead $pageStreamRead): string;
}
