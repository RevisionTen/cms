<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageShiftElementCommand;
use RevisionTen\CMS\Event\PageShiftElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageShiftElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * Shifts an item in an array one down.
     *
     * @param array $array
     * @param int   $item
     *
     * @return array
     */
    private static function down(array $array, int $item): array
    {
        if (count($array) - 1 > $item) {
            $b = array_slice($array, 0, $item, true);
            $b[] = $array[$item + 1];
            $b[] = $array[$item];
            $b += array_slice($array, $item + 2, count($array), true);

            return $b;
        } else {
            return $array;
        }
    }

    /**
     * Shifts an item in an array one up.
     *
     * @param array $array
     * @param int   $item
     *
     * @return array
     */
    private static function up(array $array, int $item): array
    {
        if ($item > 0 && $item < count($array)) {
            $b = array_slice($array, 0, ($item - 1), true);
            $b[] = $array[$item];
            $b[] = $array[$item - 1];
            $b += array_slice($array, ($item + 1), count($array), true);

            return $b;
        } else {
            return $array;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();

        $uuid = $payload['uuid'];
        $direction = $payload['direction'];

        // A function that shifts all matching elements in a provided direction.
        $shiftFunction = function (&$element, &$collection) use ($direction, $uuid) {
            if (null !== $collection) {
                // Get the key of the item that will shift.
                $itemKey = null;
                foreach ($collection as $key => $subElement) {
                    if ($subElement['uuid'] === $uuid) {
                        $itemKey = $key;
                        continue;
                    }
                }

                if (null !== $itemKey && 'up' === $direction) {
                    $collection = self::up($collection, $itemKey);
                } elseif (null !== $itemKey && 'down' === $direction) {
                    $collection = self::down($collection, $itemKey);
                }
            }
        };

        self::onElement($aggregate, $uuid, $shiftFunction);

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return PageShiftElementCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageShiftElementEvent($command);
    }

    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();
        // The uuid to shift.
        $uuid = $payload['uuid'];
        $element = self::getElement($aggregate, $uuid);

        if (!isset($uuid)) {
            $this->messageBus->dispatch(new Message(
                'No uuid to shift is set',
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
        } elseif (!isset($payload['direction']) || ('up' !== $payload['direction'] && 'down' !== $payload['direction'])) {
            $this->messageBus->dispatch(new Message(
                'Shift direction is not set',
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
