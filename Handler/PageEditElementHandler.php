<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageEditElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use function is_string;

final class PageEditElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        // Add to elements.
        $data = $payload['data'];
        $uuid = $payload['uuid'];

        // A function that updates the elements data by merging it with the new data.
        $updateDataFunction = static function (&$element, &$collection) use ($data) {
            $element['data'] = array_merge($element['data'], $data);
        };
        self::onElement($aggregate, $uuid, $updateDataFunction);

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageEditElementEvent(
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
        // The uuid to edit.
        $uuid = $payload['uuid'] ?? null;
        $element = is_string($uuid) ? self::getElement($aggregate, $uuid) : null;

        if (null === $uuid) {
            throw new CommandValidationException(
                'No uuid to edit is set',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (!isset($payload['data'])) {
            throw new CommandValidationException(
                'No data set',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (!$element) {
            throw new CommandValidationException(
                'Element with this uuid was not found '.$uuid,
                CODE_CONFLICT,
                NULL,
                $command
            );
        }

        return true;
    }
}
