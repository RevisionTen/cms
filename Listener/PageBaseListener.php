<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Listener;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;

abstract class PageBaseListener
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /**
     * PageBaseListener constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = LegacyEventDispatcherProxy::decorate($eventDispatcher);
    }
}
