<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception using the API error envelope:
 * {"error": "...", "message": "...", "details": {...}}
 */
class ApiExceptionRenderer
{
    /**
     * Machine readable error codes for the HTTP statuses we expose.
     */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        406 => 'not_acceptable',
        415 => 'unsupported_media_type',
        429 => 'too_many_requests',
    ];

    public function __invoke(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return $this->validation($e);
        }

        if ($e instanceof AuthenticationException) {
            return $this->error('unauthorized', 'Authentication is required', 401);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return $this->error(
                self::CODES[$status] ?? 'http_error',
                $this->httpMessage($e, $status),
                $status,
                headers: $e->getHeaders(),
            );
        }

        $message = config('app.debug') ? $e->getMessage() : 'Server error';

        return $this->error('server_error', $message, 500);
    }

    private function validation(ValidationException $e): JsonResponse
    {
        $failed = $e->validator->failed();
        $field = array_key_first($failed) ?? array_key_first($e->errors());
        $rule = array_key_first($failed[$field] ?? []);

        return $this->error('validation_error', $e->getMessage(), 400, [
            'field' => $field,
            'code' => $rule ? Str::snake($rule) : 'invalid',
            'errors' => $e->errors(),
        ]);
    }

    private function httpMessage(HttpExceptionInterface $e, int $status): string
    {
        if ($status === 404) {
            return 'Resource not found';
        }

        return $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error');
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @param  array<string, string>  $headers
     */
    private function error(string $error, string $message, int $status, ?array $details = null, array $headers = []): JsonResponse
    {
        $body = ['error' => $error, 'message' => $message];

        if ($details !== null) {
            $body['details'] = $details;
        }

        return new JsonResponse($body, $status, $headers);
    }
}
