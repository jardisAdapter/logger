<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Helpers;

use JardisAdapter\Logger\Handler\LogCommand;

class StreamHelper
{
    /**
     * @return resource
     */
    public static function createMemoryStream()
    {
        $stream = fopen('php://memory', 'r+');
        if (!$stream) {
            throw new \RuntimeException('Failed to create memory stream');
        }
        return $stream;
    }

    /**
     * @param resource $stream
     */
    public static function getStreamContent($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream) ?: '';
    }

    /**
     * @param resource $stream
     */
    public static function getStreamContentAsJson($stream): array
    {
        $content = self::getStreamContent($stream);
        $decoded = json_decode(trim($content), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param resource $stream
     */
    public static function invokeLoggerAndGetContent(
        LogCommand $logger,
        string $level,
        string $message,
        array $data = []
    ): string {
        $stream = self::createMemoryStream();
        $logger->setStream($stream);
        $logger($level, $message, $data);
        return self::getStreamContent($stream);
    }

    /**
     * @param resource $stream
     */
    public static function invokeLoggerAndGetJson(
        LogCommand $logger,
        string $level,
        string $message,
        array $data = []
    ): array {
        $stream = self::createMemoryStream();
        $logger->setStream($stream);
        $logger($level, $message, $data);
        return self::getStreamContentAsJson($stream);
    }
}
