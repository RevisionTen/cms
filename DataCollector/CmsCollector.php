<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataCollector;

use Exception;
use RevisionTen\CQRS\Message\Message;
use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsCollector extends DataCollector
{
    /**
     * @var MessageBus
     */
    protected $messageBus;

    /**
     * CmsCollector constructor.
     *
     * @param MessageBus $messageBus
     */
    public function __construct(MessageBus $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    /**
     * @param Request        $request
     * @param Response       $response
     * @param Exception|null $exception
     */
    public function collect(Request $request, Response $response, Exception $exception = null): void
    {
        /** @var Message[] $messages */
        $messages = $this->messageBus->getMessages();

        $messagesData = [];
        foreach ($messages as $message) {
            $messagesData[] = [
                'message' => $message->message,
                'code' => $message->code,
                'commandUuid' => $message->commandUuid,
                'aggregateUuid' => $message->aggregateUuid,
                'created' => $message->created,
                'exception' => $message->exception ? $message->exception->getTraceAsString() : null,
                'context' => $message->context,
            ];
        }

        $this->data = [
            'messages' => $messagesData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function reset(): void
    {
        $this->data = [];
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName(): string
    {
        return 'cms.cms_collector';
    }

    /**
     * @return mixed
     */
    public function getMessages()
    {
        return $this->data['messages'];
    }
}
