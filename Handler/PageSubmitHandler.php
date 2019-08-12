<?php

declare(strict_types=1);

namespace RevisionTen\CMS\Handler;

use RevisionTen\CMS\Event\PageSubmitEvent;
use RevisionTen\CMS\Model\Page;
use RevisionTen\CQRS\Exception\CommandValidationException;
use RevisionTen\CQRS\Interfaces\AggregateInterface;
use RevisionTen\CQRS\Interfaces\CommandInterface;
use RevisionTen\CQRS\Interfaces\EventInterface;
use RevisionTen\CQRS\Interfaces\HandlerInterface;

final class PageSubmitHandler extends PageBaseHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(EventInterface $event, AggregateInterface $aggregate): AggregateInterface
    {
        /** @var Page $aggregate */
        $aggregate->state = Page::STATE_STAGED;

        return $aggregate;
    }

    /**
     * {@inheritdoc}
     */
    public function createEvent(CommandInterface $command): EventInterface
    {
        return new PageSubmitEvent(
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

        if ($aggregate->getVersion() === 0) {
            throw new CommandValidationException(
                'You cannot submit an aggregate with no version',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        if (empty($payload['grantedBy'])) {
            throw new CommandValidationException(
                'You must chose the user which granted the submit',
                CODE_BAD_REQUEST,
                NULL,
                $command
            );
        }

        return true;
    }
}
