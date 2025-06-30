<?php

namespace Exxtensio\SqsExtension;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Arr;

class SqsService
{
    public function __construct(
        protected SqsClient $client
    ) {}

    public function send(array $data, string $queue, bool $fifo = true): void
    {
        $queueUrl = rtrim(config('queue.connections.sqs.prefix'), '/') . '/' . ltrim($queue, '/');
        if ($fifo && !str_ends_with($queueUrl, '.fifo')) $queueUrl .= '.fifo';

        $message = ['QueueUrl' => $queueUrl, 'MessageBody' => json_encode($data)];
        if ($fifo) $message['MessageGroupId'] = Arr::get($data, 'id', 'default');
        $this->client->sendMessage($message);
    }
}