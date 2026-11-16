<?php

namespace App\Exceptions;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExceptionReporter
{
    public function register(Exceptions $exceptions): void
    {
        $exceptions->report(function (Throwable $exception): void {
            $this->slack($exception);
        });
    }

    private function slack(Throwable $exception): void
    {
        try {
            Log::channel('slack')->error(
                $this->slackMessage($exception),
                [
                    'exception' => $exception,
                ]
            );
        } catch (Throwable) {
            /*
             * Slack being unavailable must never affect
             * the application or original exception.
             */
        }
    }

    private function slackMessage(Throwable $exception): string
    {
        $request = request();

        $method = $request instanceof Request
            ? $request->method()
            : 'CLI';

        $url = $request instanceof Request
            ? $request->fullUrl()
            : null;

        return sprintf(
            "[%s] %s\n%s\n%s %s\n%s:%d",
            strtoupper(app()->environment()),
            $exception::class,
            $exception->getMessage(),
            $method,
            $url ?? 'console',
            $exception->getFile(),
            $exception->getLine(),
        );
    }
}
