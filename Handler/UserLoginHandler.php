<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\UserLoginCommand;
use RevisionTen\CMS\Event\UserLoginEvent;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Handler\Handler;

final class UserLoginHandler extends Handler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var UserAggregate $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();

        $device = $payload['device'];
        $ip = $payload['ip'];

        // Add device to list of known devices.
        if (!in_array($device, $aggregate->devices)) {
            $aggregate->devices[] = $device;
        }

        // Add IP to list of known ips.
        if (!in_array($ip, $aggregate->ips)) {
            $aggregate->ips[] = $ip;
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return UserLoginCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new UserLoginEvent($command);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        return true;
    }
}
