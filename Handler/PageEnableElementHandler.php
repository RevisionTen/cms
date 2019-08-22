<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageEnableElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use function is_string;

final class PageEnableElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();
        $uuid = $payload['uuid'];

        // A function that enables the element.
        $enableElementFunction = static function (&$element, &$collection) {
            $element['enabled'] = true;
        };
        self::onElement($aggregate, $uuid, $enableElementFunction);

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageEnableElementEvent(
            $command->getAggregateUuid(),
            $command->getUuid(),
            $command->getOnVersion() + 1,
            $command->getUser(),
            $command->getPayload()
        );
    }

    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();
        // The uuid to enable.
        $uuid = $payload['uuid'] ?? null;
        $element = is_string($uuid) ? self::getElement($aggregate, $uuid) : null;

        if (null === $uuid) {
            throw new CommandValidationException(
                'No uuid to enable is set',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (!$element) {
            throw new CommandValidationException(
                'Element with this uuid was not found'.$uuid,
                CODE_CONFLICT,
                NULL,
                $command
            );
        }

        return true;
    }
}
