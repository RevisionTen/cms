<?php

declare(strict_types=1);

namespace RevisionTen\CMS\DataCollector;

use RevisionTen\CQRS\Services\MessageBus;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsCollector extends DataCollector
{
    /** @var MessageBus */
    protected $messageBus;

    public function __construct(MessageBus $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        /** @var \RevisionTen\CQRS\Message\Message[] $messages */
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

    public function reset()
    {
        $this->data = [];
    }

    public function getName()
    {
        return 'cms.cms_collector';
    }

    public function getMessages()
    {
        return $this->data['messages'];
    }
}
