<?php

namespace Modules\Unipotence\Infrastructure\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use LogicException;
use Modules\Unipotence\Domain\Data\UnipotenceContext;
use Modules\Unipotence\Domain\Data\UnipotenceOutcome;
use Modules\Unipotence\Domain\Exceptions\InvalidUnipotenceKey;
use Modules\Unipotence\Domain\Exceptions\UnipotenceKeyRequired;
use Symfony\Component\HttpFoundation\Response;

final class UnipotenceHttpAdapter
{
    /**
     * @param  list<string>  $replayHeaders
     */
    public function __construct(
        private readonly int $maxKeyLength,
        private readonly int $maxResponseBytes,
        private readonly array $replayHeaders,
    ) {}

    public function context(Request $request): UnipotenceContext
    {
        $keys = array_values(array_filter([
            trim((string) $request->header('Idempotency-Key', '')),
            trim((string) $request->header('Unipotence-Key', '')),
            ...$this->bodyKeys($request),
        ], static fn (string $key): bool => $key !== ''));

        if (count(array_unique($keys)) > 1) {
            throw new InvalidUnipotenceKey($this->maxKeyLength);
        }

        $key = $keys[0] ?? '';

        if ($key === '') {
            throw new UnipotenceKeyRequired;
        }

        if (strlen($key) > $this->maxKeyLength
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $key) !== 1) {
            throw new InvalidUnipotenceKey($this->maxKeyLength);
        }

        return new UnipotenceContext(
            scope: $this->scope($request),
            key: $key,
            requestHash: $this->requestHash($request),
            reference: $key,
        );
    }

    public function outcome(Response $response): UnipotenceOutcome
    {
        $body = $this->responseBody($response);
        $encodedBody = json_encode($body, JSON_THROW_ON_ERROR);

        return new UnipotenceOutcome(
            status: $response->getStatusCode(),
            body: $body,
            headers: $this->responseHeaders($response),
            replayable: $response->getContent() !== false
                && strlen($encodedBody) <= $this->maxResponseBytes,
        );
    }

    public function scope(Request $request): string
    {
        $user = $request->user('api') ?? $request->user();

        if ($user instanceof Authenticatable) {
            return sprintf('%s:%s', $user::class, $user->getAuthIdentifier());
        }

        throw new LogicException('Unipotence requires an authenticated principal scope.');
    }

    /** @return list<string> */
    private function bodyKeys(Request $request): array
    {
        return array_map(
            static fn (mixed $key): string => trim(is_string($key) ? $key : ''),
            [
                $request->input('idempotency_key'),
                $request->input('idempotency-key'),
                $request->input('Idempotency-Key'),
                $request->input('reference'),
                $request->input('tx_ref'),
            ],
        );
    }

    /** @param array<string, mixed> $payload */
    private function withoutKeyFields(array $payload): array
    {
        unset(
            $payload['idempotency_key'],
            $payload['idempotency-key'],
            $payload['Idempotency-Key'],
            $payload['reference'],
            $payload['tx_ref'],
        );

        return $payload;
    }

    private function requestHash(Request $request): string
    {
        $query = $this->canonicalize($request->query->all());
        $content = $request->getContent();

        if ($request->isJson() && $content !== '') {
            try {
                $payload = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
                if (is_array($payload)) {
                    $payload = $this->withoutKeyFields($payload);
                }

                $content = json_encode(
                    $this->canonicalize($payload),
                    JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
                );
            } catch (JsonException) {
                // Invalid JSON is hashed byte-for-byte and rejected by validation later.
            }
        } elseif ($request->request->count() > 0) {
            $content = json_encode(
                $this->canonicalize($this->withoutKeyFields($request->request->all())),
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            );
        }

        return hash('sha256', implode("\n", [
            $request->method().' '.$request->path(),
            trim((string) $request->headers->get('Content-Type')),
            json_encode($query, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            $content,
        ]));
    }

    /** @return array<string, mixed> */
    private function responseBody(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            $body = $response->getData(true);

            return is_array($body) ? $body : ['data' => $body];
        }

        $content = $response->getContent();
        $decoded = is_string($content) ? json_decode($content, true) : null;

        return is_array($decoded) ? $decoded : ['content' => $content];
    }

    /** @return array<string, string> */
    private function responseHeaders(Response $response): array
    {
        $headers = [];

        foreach ($this->replayHeaders as $name) {
            $value = $response->headers->get($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
