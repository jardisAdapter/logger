<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Enricher;

use JardisAdapter\Logger\Contract\LogEnricherInterface;

/**
 * Class LogWebRequest
 *
 * This class implements LogEnricherInterface to enhance logging functionality by incorporating
 * request-specific information, such as the URL, user agent, request method, and request data.
 * The additional data is retrieved and merged with the parent log data.
 */
class LogWebRequest implements LogEnricherInterface
{
    /**
     * Invoke method to retrieve and merge additional log data with request-specific information.
     *
     * @return array<string, mixed> An array containing the merged log data and request-specific information
     * such as URL, user agent, request method, and request data.
     */
    public function __invoke(): array
    {
        $clientIp = (new LogClientIp())();

        return [
            'client_ip'      => $clientIp,
            'request_url'    => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'method_data'    => count($_GET) ? $_GET : $_POST
        ];
    }
}
