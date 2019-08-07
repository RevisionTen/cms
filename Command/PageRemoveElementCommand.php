<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\PageRemoveElementHandler;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class PageRemoveElementCommand extends Command implements CommandInterface
{
    public const HANDLER = PageRemoveElementHandler::class;
    public const AGGREGATE = Page::class;

    /**
     * {@inheritdoc}
     */
    public function getHandlerClass(): string
    {
        return self::HANDLER;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateClass(): string
    {
        return self::AGGREGATE;
    }
}
