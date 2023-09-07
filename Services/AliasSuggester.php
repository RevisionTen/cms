<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Services;

use RevisionTen\CMS\Entity\PageStreamRead;
use RevisionTen\CMS\Interfaces\AliasSuggesterInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class AliasSuggester implements AliasSuggesterInterface
{
    private array $config;

    private AsciiSlugger $slugger;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->slugger = new AsciiSlugger($config['slugger_locale'] ?? 'de');
    }

    public function suggest(PageStreamRead $pageStreamRead): string
    {
        $alias_prefix = $this->config['page_templates'][$pageStreamRead->getTemplate()]['alias_prefix'][$pageStreamRead->getLanguage()] ?? '/';

        return $alias_prefix.$this->slugger->slug($pageStreamRead->getTitle())->lower()->toString();
    }
}
