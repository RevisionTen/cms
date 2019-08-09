<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageDuplicateElementCommand;
use RevisionTen\CMS\Event\PageDuplicateElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;
use Ramsey\Uuid\Uuid;

final class PageDuplicateElementHandler extends PageBaseHandler implements HandlerInterface
{
    private function assignNewUuid(array $element, string $commandUuid): array
    {
        // Convert old uuid to hash (in 16char bytes) and use it as a seed.
        $seed = $commandUuid.'-'.$element['uuid'];
        $seed = md5($seed, true);

        $newUuid = Uuid::fromBytes($seed)->toString();

        // Set new Uuid.
        $element['uuid'] = $newUuid;

        if (isset($element['elements']) && \is_array($element['elements'])) {
            foreach ($element['elements'] as $key => $subElement) {
                $element['elements'][$key] = $this->assignNewUuid($subElement, $commandUuid);
            }
        }

        return $element;
    }

    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();

        $commandUuid = $event->getCommandUuid();
        $uuid = $payload['uuid'];

        // A function that duplicates all matching elements.
        $duplicateFunction = function (&$element, &$collection) use ($uuid, $commandUuid) {
            if (null !== $collection) {
                // Get the key of the item that will duplicate.
                $itemKey = null;
                foreach ($collection as $key => $subElement) {
                    if ($subElement['uuid'] === $uuid) {
                        $itemKey = $key;
                        continue;
                    }
                }

                if (null !== $itemKey) {
                    // Copy element.
                    $duplicatedElement = $element;

                    // Set new uuids to element and subelements.
                    $duplicatedElement = $this->assignNewUuid($duplicatedElement, $commandUuid);

                    // Insert duplicated element into collection.
                    array_splice($collection, $itemKey + 1, 0, [$duplicatedElement]);
                }
            }
        };

        self::onElement($aggregate, $uuid, $duplicateFunction);

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageDuplicateElementEvent(
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
        // The uuid to duplicate.
        $uuid = $payload['uuid'] ?? null;
        $element = \is_string($uuid) ? self::getElement($aggregate, $uuid) : null;

        if (null === $uuid) {
            $this->messageBus->dispatch(new Message(
                'No uuid to duplicate is set',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } elseif (!$element) {
            $this->messageBus->dispatch(new Message(
                'Element with this uuid was not found'.$uuid,
                CODE_CONFLICT,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        } else {
            return true;
        }
    }
}
