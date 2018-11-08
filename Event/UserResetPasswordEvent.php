<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Event;

use RevisionTen\CMS\Command\UserResetPasswordCommand;
use RevisionTen\CMS\Listener\UserResetPasswordListener;
use RevisionTen\CQRS\Event\Event;
use RevisionTen\CQRS\Interfaces\EventInterface;

final class UserResetPasswordEvent extends Event implements EventInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return UserResetPasswordCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public static function getListenerClass(): string
    {
        return UserResetPasswordListener::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return 'User password reset';
    }

    /**
     * {@inheritdoc}
     */
    public static function getCode(): int
    {
        return CODE_OK;
    }
}
