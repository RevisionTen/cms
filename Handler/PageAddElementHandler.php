<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageAddElementCommand;
use RevisionTen\CMS\Event\PageAddElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageAddElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();
        $elementName = $payload['elementName'];
        $data = $payload['data'];

        // Build element data.
        $newElement = [
            'uuid' => $command->getUuid(),
            'elementName' => $elementName,
            'data' => $data,
        ];

        // Add to elements.
        $parentUuid = isset($payload['parent']) ? $payload['parent'] : null;

        if ($parentUuid && is_string($parentUuid)) {
            // A function that add the new element to the target parent.
            $addElementFunction = function (&$element, &$collection) use ($newElement) {
                if (!isset($element['elements'])) {
                    $element['elements'] = [];
                }
                $element['elements'][] = $newElement;
            };
            self::onElement($aggregate, $parentUuid, $addElementFunction);
        } else {
            // Add to page root.
            $aggregate->elements[] = $newElement;
        }

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return PageAddElementCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageAddElementEvent($command);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();

        if (!isset($payload['elementName'])) {
            $this->messageBus->dispatch(new Message(
                'No element type set',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } elseif (!isset($payload['data'])) {
            $this->messageBus->dispatch(new Message(
                'No data set',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } else {
            return true;
        }
    }
}
