<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Event;

use RevisionTen\CMS\Model\Page;
use RevisionTen\CMS\Handler\PageLockHandler;
use RevisionTen\CQRS\Event\AggregateEvent;
use RevisionTen\CQRS\Interfaces\EventInterface;

final class PageLockEvent extends AggregateEvent implements EventInterface
{
    public static function getAggregateClass(): string
    {
        return Page::class;
    }

    public static function getHandlerClass(): string
    {
        return PageLockHandler::class;
    }

    public function getMessage(): string
    {
        return 'Page Locked';
    }
}
