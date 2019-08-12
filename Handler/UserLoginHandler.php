<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\UserLoginEvent;
use RevisionTen\CMS\Model\UserAggregate;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use function in_array;

final class UserLoginHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var UserAggregate $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        $device = $payload['device'];
        $ip = $payload['ip'];

        // Add device to list of known devices.
        if (!in_array($device, $aggregate->devices, false)) {
            $aggregate->devices[] = $device;
        }

        // Add IP to list of known ips.
        if (!in_array($ip, $aggregate->ips, false)) {
            $aggregate->ips[] = $ip;
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new UserLoginEvent(
            $command->getAggregateUuid(),
            $command->getUuid(),
            $command->getOnVersion() + 1,
            $command->getUser(),
            $command->getPayload()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        return true;
    }
}
