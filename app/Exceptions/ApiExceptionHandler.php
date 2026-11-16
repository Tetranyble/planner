<?php

namespace App\Exceptions;

use App\Common\ApiResponse;
use App\Exceptions\Accounting\InsufficientFunds as AccountInsufficientFunds;
use App\Exceptions\Vending\VendSagaConflict;
use App\Exceptions\Vending\VendSagaOutcomeUnknown;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Unipotence\Domain\Contracts\DefinitelyNotDispatched;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    public function __construct(
        private readonly ApiResponse $response
    ) {}

    public function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $this->expectsJson($request)
        );

        $exceptions->render(
            fn (ValidationException $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->validationError($e)
            )
        );

        $exceptions->render(
            fn (AccountInsufficientFunds|VendSagaConflict $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->error(
                    message: $e->getMessage(),
                    statusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
                    exception: $e,
                )
            )
        );

        $exceptions->render(
            fn (VendSagaOutcomeUnknown $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->error(
                    message: $e->getMessage(),
                    statusCode: Response::HTTP_CONFLICT,
                    exception: $e,
                )
            )
        );

        $exceptions->render(
            fn (AuthenticationException $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->unauthorized()
            )
        );

        $exceptions->render(
            fn (AuthorizationException $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->forbidden()
            )
        );

        $exceptions->render(
            fn (ModelNotFoundException $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->notFound('Resource not found.')
            )
        );

        $exceptions->render(
            fn (NotFoundHttpException $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->notFound()
            )
        );

        $exceptions->render(
            fn (HttpExceptionInterface $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->httpException($e)
            )
        );

        $exceptions->render(
            fn (Throwable $e, Request $request) => $e instanceof DefinitelyNotDispatched
                ? $this->whenApi(
                    $request,
                    fn () => $this->response->error(
                        message: $e->getMessage(),
                        statusCode: 425,
                        exception: $e,
                        headers: [
                            'Retry-After' => '1',
                            'Transient-Error' => 'true',
                        ],
                    )
                )
                : null
        );

        $exceptions->render(
            fn (Throwable $e, Request $request) => $this->whenApi(
                $request,
                fn () => $this->response->internalError($e)
            )
        );
    }

    private function httpException(
        HttpExceptionInterface $exception
    ): JsonResponse {
        $statusCode = $exception->getStatusCode();

        return $this->response->error(
            message: $this->httpMessage($statusCode),
            statusCode: $statusCode,
            exception: $exception,
            headers: $exception->getHeaders(),
        );
    }

    private function httpMessage(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            Response::HTTP_UNAUTHORIZED => Response::$statusTexts[Response::HTTP_UNAUTHORIZED],
            Response::HTTP_FORBIDDEN => Response::$statusTexts[Response::HTTP_FORBIDDEN],
            Response::HTTP_NOT_FOUND => Response::$statusTexts[Response::HTTP_NOT_FOUND],
            Response::HTTP_METHOD_NOT_ALLOWED => Response::$statusTexts[Response::HTTP_METHOD_NOT_ALLOWED],
            419 => 'Page expired.',
            Response::HTTP_UNPROCESSABLE_ENTITY => Response::$statusTexts[Response::HTTP_UNPROCESSABLE_ENTITY],
            Response::HTTP_TOO_MANY_REQUESTS => Response::$statusTexts[Response::HTTP_TOO_MANY_REQUESTS],
            Response::HTTP_INTERNAL_SERVER_ERROR => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR],
            Response::HTTP_SERVICE_UNAVAILABLE => Response::$statusTexts[Response::HTTP_SERVICE_UNAVAILABLE],
            default => 'Request failed.',
        };
    }

    private function expectsJson(Request $request): bool
    {
        return $request->is('api/*')
            || $request->expectsJson();
    }

    private function whenApi(
        Request $request,
        callable $callback
    ): mixed {
        if (! $this->expectsJson($request)) {
            return null;
        }

        return $callback();
    }
}
