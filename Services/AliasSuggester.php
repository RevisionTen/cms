<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Cocur\Slugify\Slugify;
use RevisionTen\CMS\Entity\PageStreamRead;
use RevisionTen\CMS\Interfaces\AliasSuggesterInterface;

class AliasSuggester implements AliasSuggesterInterface
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function suggest(PageStreamRead $pageStreamRead): string
    {
        $slugify = new Slugify();

        $alias_prefix = $this->config['page_templates'][$pageStreamRead->getTemplate()]['alias_prefix'][$pageStreamRead->getLanguage()] ?? '/';

        return $alias_prefix.$slugify->slugify($pageStreamRead->getTitle());
    }
}
