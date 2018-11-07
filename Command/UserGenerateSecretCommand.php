<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Command;

use RevisionTen\CMS\Handler\UserGenerateSecretHandler;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Command\Command;
use RevisionTen\CQRS\Interfaces\CommandInterface;

final class UserGenerateSecretCommand extends Command implements CommandInterface
{
    /**
     * {@inheritdoc}
     */
    public function getHandlerClass(): string
    {
        return UserGenerateSecretHandler::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateClass(): string
    {
        return UserAggregate::class;
    }
}
