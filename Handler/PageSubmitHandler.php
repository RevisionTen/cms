<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Command\PageSubmitCommand;
use RevisionTen\CMS\Event\PageSubmitEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;
use RevisionTen\CQRS\Message\Message;

final class PageSubmitHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(CommandInterface $command, AggregateInterface $aggregate): AggregateInterface
    {
        /** @var Page $aggregate */
        $aggregate->state = Page::STATE_STAGED;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public static function getCommandClass(): string
    {
        return PageSubmitCommand::class;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageSubmitEvent($command);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCommand(CommandInterface $command, AggregateInterface $aggregate): bool
    {
        $payload = $command->getPayload();

        if (isset($payload['grantedBy']) && !empty($payload['grantedBy']) && $aggregate->getVersion() > 0) {
            return true;
        } else {
            $this->messageBus->dispatch(new Message(
                'You must chose the user which granted the submit',
                CODE_BAD_REQUEST,
                $command->getUuid(),
                $command->getAggregateUuid()
            ));

            return false;
        }
    }
}
