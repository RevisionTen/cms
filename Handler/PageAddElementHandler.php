<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageAddElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use function is_string;

final class PageAddElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();
        $elementName = $payload['elementName'];
        $data = $payload['data'];

        // Build element data.
        $newElement = [
            'uuid' => $event->getCommandUuid(),
            'elementName' => $elementName,
            'data' => $data,
        ];

        // Add to elements.
        $parentUuid = $payload['parent'] ?? null;

        if ($parentUuid && is_string($parentUuid)) {
            // A function that add the new element to the target parent.
            $addElementFunction = static function (&$element, &$collection) use ($newElement) {
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

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageAddElementEvent(
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
        $payload = $command->getPayload();

        if (!isset($payload['elementName'])) {
            throw new CommandValidationException(
                'No element type set',
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

        return true;
    }
}
