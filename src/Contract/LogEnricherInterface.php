<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Contract;

interface LogEnricherInterface
{
    /**
     * Invokes the object as a function.
     *
     * @return string|array<string, string> Returns an array or string data upon invocation.
     */
    public function __invoke();
}
