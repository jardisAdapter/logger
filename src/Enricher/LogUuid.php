<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Enricher;

/**
 * Class LogUuid
 *
 * Provides a mechanism to generate a universally unique identifier (UUID).
 * When invoked, it returns the generated UUID string.
 */
class LogUuid
{
    /**
     * Generates a random UUID v4 string.
     *
     * @return string The generated UUID v4 string.
     */
    public function __invoke(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
