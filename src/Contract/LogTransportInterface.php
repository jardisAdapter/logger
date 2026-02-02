<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Contract;

/**
 * Transport layer for delivering formatted log payloads
 * Handles delivery mechanism without knowing about log structure
 */
interface LogTransportInterface
{
    /**
     * Send a payload to the destination
     *
     * @param string $destination Target (URL, file path, socket, etc.)
     * @param string $payload Formatted payload ready for delivery
     * @param array<string, mixed> $options Transport-specific options
     * @return bool Success status
     */
    public function send(string $destination, string $payload, array $options = []): bool;
}
