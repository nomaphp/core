<?php

namespace Noma\Core\Runners;

use Noma\Core\Runner;

class FrankenPhpWorker implements Runner
{
    public function handleRequest(callable $handler): void
    {
        if (!function_exists('frankenphp_handle_request')) {
            return;
        }

        // Config max requests
        $maxReqs = (int)($_SERVER['MAX_REQUESTS'] ?? 0);

        // Event loop
        for ($reqs = 0; !$maxReqs || $reqs < $maxReqs; $reqs++) {
            $keepRunning = \frankenphp_handle_request($handler);

            gc_collect_cycles();

            if (!$keepRunning) {
                break;
            }
        }
    }
}