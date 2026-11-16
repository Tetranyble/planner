<?php

namespace Modules\Unipotence\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Unipotence\Application\Actions\ClaimUnipotenceAction;
use Modules\Unipotence\Application\Actions\RecordUnipotenceFailureAction;
use Modules\Unipotence\Application\Actions\RecordUnipotenceOutcomeAction;
use Modules\Unipotence\Infrastructure\Http\UnipotenceHttpAdapter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UnipotenceGateMiddleware
{
    public function __construct(
        private readonly UnipotenceHttpAdapter $http,
        private readonly ClaimUnipotenceAction $claim,
        private readonly RecordUnipotenceOutcomeAction $recordOutcome,
        private readonly RecordUnipotenceFailureAction $recordFailure,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $claim = $this->claim->execute($this->http->context($request));

        $request->merge([
            'idempotency_key' => $claim->context->key,
            'reference' => $claim->context->key,
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->recordFailure->execute($claim, $exception);

            throw $exception;
        }

        $response->headers->set('Idempotency-Key', $claim->context->key);
        $this->recordOutcome->execute($claim, $this->http->outcome($response));

        return $response;
    }
}
