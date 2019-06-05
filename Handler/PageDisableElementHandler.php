<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageDisableElementCommand;
use RevisionTen\CMS\Event\PageDisableElementEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageDisableElementHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @var Page $aggregate
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        $payload = $command->getPayload();
        $uuid = $payload['uuid'];

        // A function that disables the element.
        $disableElementFunction = static function (&$element, &$collection) {
            $element['enabled'] = false;
        };
        self::onElement($aggregate, $uuid, $disableElementFunction);

        $aggregate->state = Page::STATE_DRAFT;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return PageDisableElementCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageDisableElementEvent($command);
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
        $uuid = $payload['uuid'] ?? null;
        $element = \is_string($uuid) ? self::getElement($aggregate, $uuid) : null;

        if (null === $uuid) {
            $this->messageBus->dispatch(new Message(
                'No uuid to disable is set',
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
