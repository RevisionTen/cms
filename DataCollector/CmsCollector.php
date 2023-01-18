<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataCollector;

use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CmsCollector extends DataCollector
{
    protected MessageBus $messageBus;

    public function __construct(MessageBus $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
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

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'cms.cms_collector';
    }

    public function getMessages()
    {
        return $this->data['messages'];
    }
}
