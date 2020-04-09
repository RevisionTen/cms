<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageChangeElementPaddingEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class PageChangeElementPaddingHandler extends PageBaseHandler implements HandlerInterface
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
        $padding = $payload['padding'];

        // A function that changes the padding
        $changePaddingFunction = static function (&$element, &$collection) use ($padding) {
            if (empty($element['data']['settings']['paddings'])) {
                $element['data']['settings']['paddings'] = [];
            }
            // Update element.
            $element['data']['settings']['paddings'][0] = $padding;
        };
        self::onElement($aggregate, $uuid, $changePaddingFunction);

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageChangeElementPaddingEvent(
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
        $uuid = $payload['uuid'] ?? null;
        $element = is_string($uuid) ? self::getElement($aggregate, $uuid) : null;
        $padding = $payload['padding'];

        // Check if padding settings are valid.
        $paddingValid = true;
        $validBreakpoints = ['xs', 'sm', 'md', 'lg', 'xl'];
        $validKeys = ['breakpoint', 'top', 'bottom', 'left', 'right'];
        if (!is_array($padding)) {
            $paddingValid = false;
        } else {
            foreach ($padding as $key => $value) {
                if (!in_array($key, $validKeys, true)) {
                    $paddingValid = false;
                }
                // Value must be a string or null.
                if ($value !== null && !is_string($value)) {
                    $paddingValid = false;
                }
                if (is_string($value)) {
                    // Check if its a valid breakpoint.
                    if ('breakpoint' === $key && !in_array($key, $validBreakpoints, true)) {
                        $paddingValid = false;
                    }
                    // Check if its a valid padding.
                    if ('breakpoint' !== $key && ((int) $value < 0 || (int) $value > 6)) {
                        $paddingValid = false;
                    }
                }
            }
        }
        if (!$paddingValid) {
            var_dump($padding);
            throw new CommandValidationException(
                'Padding is invalid',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (null === $uuid) {
            throw new CommandValidationException(
                'No element uuid is set',
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
