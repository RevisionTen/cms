<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Event;

use RevisionTen\CMS\Command\UserGenerateSecretCommand;
use RevisionTen\CMS\Listener\UserGenerateSecretListener;
use RevisionTen\CQRS\Event\Event;
use RevisionTen\CQRS\Interfaces\EventInterface;

final class UserGenerateSecretEvent extends Event implements EventInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return UserGenerateSecretCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public static function getListenerClass(): string
    {
        return UserGenerateSecretListener::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return 'User secret generated';
    }

    /**
     * {@inheritdoc}
     */
    public static function getCode(): int
    {
        return CODE_OK;
    }
}
