<?php

namespace App\Common;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public function success(
        mixed $data = null,
        string $message = 'success',
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return $this->make(
            status: true,
            message: $message,
            data: $data,
            statusCode: $statusCode,
            headers: $headers,
        );
    }

    public function error(
        ?string $message = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $errors = [],
        ?\Throwable $exception = null,
        array $headers = []
    ): JsonResponse {
        return $this->make(
            status: false,
            message: $message ?? Response::$statusTexts[Response::HTTP_BAD_REQUEST],
            data: null,
            errors: $errors,
            exception: $exception,
            statusCode: $statusCode,
            headers: $headers,
        );
    }

    public function validationError(
        ValidationException $exception
    ): JsonResponse {
        $errors = $exception->errors();
        $message = collect($errors)
            ->flatten()
            ->first()
            ?? Response::$statusTexts[Response::HTTP_UNPROCESSABLE_ENTITY];

        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => [],
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function unauthorized(
        ?string $message = null
    ): JsonResponse {
        return $this->error(
            $message ?? Response::$statusTexts[Response::HTTP_UNAUTHORIZED],
            Response::HTTP_UNAUTHORIZED
        );
    }

    public function forbidden(
        ?string $message = null
    ): JsonResponse {
        return $this->error(
            $message ?? Response::$statusTexts[Response::HTTP_FORBIDDEN],
            Response::HTTP_FORBIDDEN
        );
    }

    public function notFound(
        ?string $message = null
    ): JsonResponse {
        return $this->error(
            $message ?? Response::$statusTexts[Response::HTTP_NOT_FOUND],
            Response::HTTP_NOT_FOUND
        );
    }

    public function internalError(
        ?\Throwable $exception = null,
        string $message = 'Internal server error'
    ): JsonResponse {
        return $this->error(
            message: $message,
            statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            exception: $exception,
        );
    }

    public function created(
        mixed $data = null,
        string $message = 'Created'
    ): JsonResponse {
        return $this->success(
            data: $data,
            message: $message ?? Response::$statusTexts[Response::HTTP_CREATED],
            statusCode: Response::HTTP_CREATED,
        );
    }

    public function noContent(
        array $headers = []
    ): JsonResponse {
        return $this->success(
            data: null,
            message: $message ?? Response::$statusTexts[Response::HTTP_NO_CONTENT],
            statusCode: Response::HTTP_NO_CONTENT,
            headers: $headers,
        );
    }

    /**
     * Return an already-formed API payload without wrapping it again.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function raw(
        array $payload,
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return response()->json($payload, $statusCode, $headers);
    }

    protected function make(
        bool $status,
        ?string $message = null,
        mixed $data = null,
        array $errors = [],
        ?\Throwable $exception = null,
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        $payload = [
            'status' => $status,
            'message' => $message ?? Response::$statusTexts[Response::HTTP_OK],
            'data' => $data,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($exception !== null && config('app.debug')) {
            $payload['exception'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode(),
            ];
        }

        return response()->json(
            $payload,
            $statusCode,
            $headers
        );
    }

    public function resource(
        JsonResource $resource,
        string $message = 'OK',
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return $this->success(
            data: $resource->resolve(request()),
            message: $message,
            statusCode: $statusCode,
            headers: $headers,
        );
    }
}
