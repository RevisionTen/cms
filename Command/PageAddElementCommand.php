<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\PageAddElementHandler;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class PageAddElementCommand extends Command implements CommandInterface
{
    public const HANDLER = PageAddElementHandler::class;
    public const AGGREGATE = Page::class;

    /**
     * {@inheritdoc}
     */
    public static function getHandlerClass(): string
    {
        return self::HANDLER;
    }

    /**
     * {@inheritdoc}
     */
    public static function getAggregateClass(): string
    {
        return self::AGGREGATE;
    }
}
