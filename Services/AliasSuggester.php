<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use Cocur\Slugify\SlugifyInterface;
use RevisionTen\CMS\Entity\PageStreamRead;
use RevisionTen\CMS\Interfaces\AliasSuggesterInterface;

class AliasSuggester implements AliasSuggesterInterface
{
    private array $config;

    private SlugifyInterface $slugify;

    public function __construct(SlugifyInterface $slugify, array $config)
    {
        $this->slugify = $slugify;
        $this->config = $config;
    }

    public function suggest(PageStreamRead $pageStreamRead): string
    {
        $alias_prefix = $this->config['page_templates'][$pageStreamRead->getTemplate()]['alias_prefix'][$pageStreamRead->getLanguage()] ?? '/';

        return $alias_prefix.$this->slugify->slugify($pageStreamRead->getTitle());
    }
}
