<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Enricher;

use JardisAdapter\Logger\Contract\LogEnricherInterface;

/**
 * Class LogClientIp
 *
 * Implements the LogEnricherInterface to provide functionality for logging the client's IP address.
 */
class LogClientIp implements LogEnricherInterface
{
    /**
     * Retrieves the client's IP address from the HTTP request.
     *
     * The method checks multiple server variables to determine the client's IP address,
     * prioritizing HTTP_CLIENT_IP and HTTP_X_FORWARDED_FOR, respectively, before falling
     * back to REMOTE_ADDR. If none of these are set, it returns 'unknown'.
     *
     * @return string The client's IP address or 'unknown' if it cannot be determined.
     */
    public function __invoke(): string
    {
        $value = 'unknown';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $value = $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $value = trim($ips[0]);
        }

        return  $_SERVER['REMOTE_ADDR'] ?? $value;
    }
}
