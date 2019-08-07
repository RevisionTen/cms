<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\UserEditHandler;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class UserEditCommand extends Command implements CommandInterface
{
    public const HANDLER = UserEditHandler::class;
    public const AGGREGATE = UserAggregate::class;

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
