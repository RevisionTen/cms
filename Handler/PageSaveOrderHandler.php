<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageSaveOrderCommand;
use RevisionTen\CMS\Event\PageSaveOrderEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageSaveOrderHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $event->getPayload();
        $order = $payload['order'];

        // Get flattened page.
        $flatPage = [];
        self::getElements($aggregate->elements, $flatPage);

        // Rebuild page from order tree.
        $treePage = self::fillTree($order, $flatPage);
        $aggregate->elements = $treePage;

        // Add leftover elements from flattened page.
        foreach ($flatPage as $element) {
            $aggregate->elements[] = $element;
        }

        return $aggregate;
    }

    /**
     * A function to rebuild the page from an page order tree.
     *
     * @param $order
     * @param $flatPage
     *
     * @return array
     */
    private static function fillTree($order, &$flatPage): array
    {
        $elements = [];

        foreach ($order as $uuid => $childrenOrder) {
            if (isset($flatPage[$uuid])) {
                $element = $flatPage[$uuid];

                if (!empty($childrenOrder)) {
                    // Add child elements.
                    $element['elements'] = self::fillTree($childrenOrder, $flatPage);
                }

                $elements[] = $element;
                unset($flatPage[$uuid]);
            }
        }

        return $elements;
    }

    /**
     * A function to create a flat array of all elements in the page.
     * It traverses the page and appends all elements to the $flatpage array.
     *
     * @param $elements
     * @param $flatPage
     */
    private static function getElements($elements, &$flatPage): void
    {
        foreach ($elements as $element) {
            // Get child elements array.
            $childElements = isset($element['elements']) && \is_array($element['elements']) && !empty($element['elements']) ? $element['elements'] : null;

            if (null !== $childElements) {
                // Unset child elements property.
                unset($element['elements']);
                // Add parent element to flat page array.
                $flatPage[$element['uuid']] = $element;
                // Add the child elements.
                self::getElements($childElements, $flatPage);
            } else {
                // Add parent element to flat page array.
                $flatPage[$element['uuid']] = $element;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageSaveOrderEvent(
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
        // The uuid to disable.
        $order = $payload['order'] ?? null;

        if (null === $order) {
            $this->messageBus->dispatch(new Message(
                'No order to save is set',
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
