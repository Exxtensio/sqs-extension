<?php

namespace Exxtensio\SqsExtension;

use Aws\Sqs\SqsClient;
use Illuminate\Support\{Collection, Arr};
use Random\RandomException;

class SqsService
{
    public function __construct(protected SqsClient $sqs)
    {
    }

    public const array DEFAULT = [
        'event' => ['type' => null, 'data' => null],
        'session' => ['id' => null, 'uniqueId' => null],
        'location' => ['hash' => null, 'host' => null, 'hostname' => null, 'href' => null, 'origin' => null, 'pathname' => null, 'port' => null, 'protocol' => null, 'search' => null, 'referrer' => null],
        'network' => ['ipAddress' => null],
        'geo' => ['latitude' => null, 'longitude' => null, 'accuracy' => null],
        'os' => ['name' => null, 'version' => null],
        'device' => ['type' => null],
        'screen' => [
            'width' => null, 'height' => null,
            'availableWidth' => null, 'availableHeight' => null,
            'innerWidth' => null, 'innerHeight' => null,
            'outerWidth' => null, 'outerHeight' => null,
            'colorDepth' => null,
            'orientationAngle' => null, 'orientationType' => null,
            'pixelDepth' => null, 'dpi' => null, 'isTouch' => null,
        ],
        'browser' => ['userAgent' => null, 'name' => null, 'version' => null, 'cookieEnabled' => null, 'language' => null, 'languages' => []],
        'connection' => ['downlink' => null, 'effectiveType' => null, 'rtt' => null, 'saveData' => null],
        'datetime' => ['locale' => null, 'calendar' => null, 'day' => null, 'month' => null, 'year' => null, 'numberingSystem' => null, 'timezone' => null],
        'payload' => [],
        'options' => ['parsePhone' => null],
    ];

    public static array $keys = [
        'event.type', 'event.data',
        'location.hash', 'location.host', 'location.hostname', 'location.href', 'location.origin',
        'location.pathname', 'location.port', 'location.protocol', 'location.search', 'location.referrer', 'location.fp',
        'session.id', 'session.uniqueId',
        'persistent.id', 'persistent.new',
        'network.ipAddress',
        'geo.latitude', 'geo.longitude', 'geo.accuracy',
        'os.name', 'os.version',
        'device.type', 'device.fp',
        'screen.width', 'screen.height', 'screen.availableWidth', 'screen.availableHeight',
        'screen.innerWidth', 'screen.innerHeight', 'screen.outerWidth', 'screen.outerHeight',
        'screen.colorDepth', 'screen.orientationAngle', 'screen.orientationType', 'screen.pixelDepth', 'screen.dpi', 'screen.isTouch',
        'browser.userAgent', 'browser.name', 'browser.version', 'browser.cookieEnabled', 'browser.language', 'browser.languages',
        'connection.downlink', 'connection.effectiveType', 'connection.rtt', 'connection.saveData',
        'datetime.locale', 'datetime.calendar', 'datetime.day', 'datetime.month', 'datetime.year', 'datetime.numberingSystem', 'datetime.timezone',
        'payload',
    ];

    public function send(array $data, string $queue, bool $fifo = true): void
    {
        $queueUrl = rtrim(config('queue.connections.sqs.prefix'), '/') . '/' . ltrim($queue, '/');
        if ($fifo && !str_ends_with($queueUrl, '.fifo')) $queueUrl .= '.fifo';

        $message = ['QueueUrl' => $queueUrl, 'MessageBody' => json_encode($data)];
        if ($fifo) $message['MessageGroupId'] = Arr::get($data, 'id', 'default');
        $this->sqs->sendMessage($message);
    }

    public function receive(string $queue, int $maxNumberOfMessages = 1, int $waitTimeSeconds = 10, int $visibilityTimeout = 30, bool $fifo = true): \Aws\Result
    {
        $queueUrl = rtrim(config('queue.connections.sqs.prefix'), '/') . '/' . ltrim($queue, '/');
        if ($fifo && !str_ends_with($queueUrl, '.fifo')) $queueUrl .= '.fifo';

        return $this->sqs->receiveMessage([
            'QueueUrl' => $queueUrl,
            'MaxNumberOfMessages' => $maxNumberOfMessages,
            'WaitTimeSeconds' => $waitTimeSeconds,
            'VisibilityTimeout' => $visibilityTimeout,
            'MessageAttributeNames' => ['All'],
        ]);
    }

    /**
     * @throws RandomException
     */
    public function setCollection(Collection $collection, $ip): Collection
    {
        $hex = bin2hex(random_bytes(16));
        $merged = array_replace_recursive(self::DEFAULT, $collection->toArray());
        if (empty($merged['network']['ipAddress'])) $merged['network']['ipAddress'] = $ip;
        if (empty($merged['session']['id'])) $merged['session']['id'] = $hex;
        if (empty($merged['session']['uniqueId'])) $merged['session']['uniqueId'] = $hex;

        return collect($merged);
    }

    public function decrypt($data, $decryptKey): false|string
    {
        list($iv, $encryptedData) = explode(':', $data);
        $iv = base64_decode($iv);
        $ciphertext = base64_decode($encryptedData);
        $key = substr($decryptKey, 0, 32);
        return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }
}