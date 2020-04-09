<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\PageChangeElementPaddingHandler;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class PageChangeElementPaddingCommand extends Command implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getHandlerClass(): string
    {
        return PageChangeElementPaddingHandler::class;
    }

    /**
     * {@inheritdoc}
     */
    public static function getAggregateClass(): string
    {
        return Page::class;
    }
}
