<?php

namespace Modules\Unipotence\Infrastructure\Exceptions;

use App\Common\ApiResponse;
use Illuminate\Foundation\Configuration\Exceptions;
use Modules\Unipotence\Domain\Exceptions\InvalidUnipotenceKey;
use Modules\Unipotence\Domain\Exceptions\OutcomeUnknown;
use Modules\Unipotence\Domain\Exceptions\ReplayResponse;
use Modules\Unipotence\Domain\Exceptions\RequestInFlight;
use Modules\Unipotence\Domain\Exceptions\UnipotenceConflict;
use Modules\Unipotence\Domain\Exceptions\UnipotenceKeyRequired;
use Modules\Unipotence\Domain\Exceptions\UnipotenceStoreUnavailable;
use Symfony\Component\HttpFoundation\Response;

final class UnipotenceExceptionHandler
{
    public function __construct(
        private readonly ApiResponse $response,
    ) {}

    public function register(Exceptions $exceptions): void
    {
        $exceptions->dontReport([
            InvalidUnipotenceKey::class,
            OutcomeUnknown::class,
            ReplayResponse::class,
            RequestInFlight::class,
            UnipotenceConflict::class,
            UnipotenceKeyRequired::class,
        ]);

        $exceptions->render(
            fn (ReplayResponse $exception) => $this->response->raw(
                $exception->responseBody,
                $exception->responseStatus,
                [...$exception->responseHeaders, 'Unipotent-Replayed' => 'true'],
            )
        );

        $exceptions->render(
            fn (UnipotenceKeyRequired|InvalidUnipotenceKey $exception) => $this->response->error(
                message: $exception->getMessage(),
                statusCode: 400,
                exception: $exception,
            )
        );

        $exceptions->render(
            fn (UnipotenceConflict $exception) => $this->response->error(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                exception: $exception,
            )
        );

        $exceptions->render(
            fn (RequestInFlight|OutcomeUnknown $exception) => $this->response->error(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_CONFLICT,
                exception: $exception,
            )
        );

        $exceptions->render(
            fn (UnipotenceStoreUnavailable $exception) => $this->response->error(
                message: $exception->getMessage(),
                statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
                exception: $exception,
                headers: [
                    'Retry-After' => '1',
                    'Transient-Error' => 'true',
                ],
            )
        );
    }
}
