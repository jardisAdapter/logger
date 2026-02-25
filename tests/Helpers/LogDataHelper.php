<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Helpers;

class LogDataHelper
{
    public static function create(array $overrides = []): array
    {
        return array_merge([
            'datetime' => new \DateTime('2023-10-19 10:30:00'),
            'context' => 'test_context',
            'level' => 'info',
            'message' => 'Test message',
            'data' => ['key' => 'value']
        ], $overrides);
    }

    public static function createWithSpecialChars(): array
    {
        return self::create([
            'context' => 'special',
            'message' => 'Special characters: " \\ /',
            'data' => ['data_key' => 'data_value']
        ]);
    }

    /**
     * @return resource
     */
    public static function createWithInvalidResource(): array
    {
        return self::create([
            'data' => fopen('php://memory', 'r')
        ]);
    }

    public static function createMultipleLevels(): array
    {
        return [
            self::create(['level' => 'debug', 'message' => 'Debug message']),
            self::create(['level' => 'info', 'message' => 'Info message']),
            self::create(['level' => 'notice', 'message' => 'Notice message']),
            self::create(['level' => 'warning', 'message' => 'Warning message']),
            self::create(['level' => 'error', 'message' => 'Error message']),
            self::create(['level' => 'critical', 'message' => 'Critical message']),
            self::create(['level' => 'alert', 'message' => 'Alert message']),
            self::create(['level' => 'emergency', 'message' => 'Emergency message']),
        ];
    }
}
