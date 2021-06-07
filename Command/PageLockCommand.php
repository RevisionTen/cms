<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\PageLockHandler;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class PageLockCommand extends Command implements CommandInterface
{
    public static function getHandlerClass(): string
    {
        return PageLockHandler::class;
    }

    public static function getAggregateClass(): string
    {
        return Page::class;
    }
}
