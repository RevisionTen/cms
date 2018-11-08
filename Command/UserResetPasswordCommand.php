<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\UserResetPasswordHandler;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class UserResetPasswordCommand extends Command implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getHandlerClass(): string
    {
        return UserResetPasswordHandler::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateClass(): string
    {
        return UserAggregate::class;
    }
}
