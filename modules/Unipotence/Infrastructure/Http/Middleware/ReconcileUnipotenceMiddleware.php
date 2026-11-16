<?php

namespace Modules\Unipotence\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Unipotence\Application\Actions\ReconcileIndeterminateUnipotenceAction;
use Modules\Unipotence\Infrastructure\Http\UnipotenceHttpAdapter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ReconcileUnipotenceMiddleware
{
    public function __construct(
        private readonly ReconcileIndeterminateUnipotenceAction $reconcile,
        private readonly UnipotenceHttpAdapter $http,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse || ! $response->isSuccessful()) {
            return $response;
        }

        $body = $response->getData(true);
        $status = is_array($body) ? data_get($body, 'data.status') : null;
        $reference = $request->input('reference');

        if (! is_string($reference)
            || ! is_string($status)
            || ! in_array($status, ['success', 'failed'], true)) {
            return $response;
        }

        try {
            $this->reconcile->execute(
                $this->http->scope($request),
                $reference,
                $this->http->outcome($response),
            );
        } catch (Throwable $exception) {
            $this->logger->critical('Failed to reconcile an indeterminate unipotence outcome.', [
                'reference_hash' => hash('sha256', $reference),
                'exception' => $exception,
            ]);
        }

        return $response;
    }
}
