<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\UserLogoutHandler;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class UserLogoutCommand extends Command implements CommandInterface
{
    public const HANDLER = UserLogoutHandler::class;
    public const AGGREGATE = UserAggregate::class;

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
