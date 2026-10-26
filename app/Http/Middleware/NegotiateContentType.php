<?php

namespace App\Http\Middleware;

use App\Support\XmlEncoder;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * Honors the Accept header: JSON by default, XML on request and 406 for
 * anything else. Error bodies are always JSON, as the API error contract
 * requires.
 */
class NegotiateContentType
{
    private const JSON = 'application/json';

    private const XML = 'application/xml';

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $format = $this->negotiate((string) $request->header('Accept'));

        if ($format === null) {
            throw new NotAcceptableHttpException('Supported formats: application/json, application/xml');
        }

        $response = $next($request);

        if ($format === self::XML && $response instanceof JsonResponse && $response->isSuccessful() && $response->getContent() !== '') {
            return new Response(
                XmlEncoder::encode($response->getData(true)),
                $response->getStatusCode(),
                ['Content-Type' => self::XML.'; charset=UTF-8'],
            );
        }

        return $response;
    }

    /**
     * Pick the supported media type with the highest quality value. JSON wins
     * ties, and a missing Accept header or wildcards also resolve to JSON.
     */
    private function negotiate(string $accept): ?string
    {
        if (trim($accept) === '') {
            return self::JSON;
        }

        $quality = [self::JSON => 0.0, self::XML => 0.0];
        $fromBrowser = false;

        foreach (explode(',', $accept) as $part) {
            [$type, $q] = $this->parse($part);
            $fromBrowser = $fromBrowser || $type === 'text/html';

            $matches = match ($type) {
                '*/*', 'application/*' => [self::JSON, self::XML],
                self::JSON, 'text/json' => [self::JSON],
                self::XML, 'text/xml' => [self::XML],
                default => [],
            };

            foreach ($matches as $match) {
                $quality[$match] = max($quality[$match], $q);
            }
        }

        if (max($quality) <= 0) {
            return null;
        }

        // Browsers (and many HTTP clients) send "text/html,...,application/xml;q=0.9,*/*;q=0.8".
        // That is not a request for XML, so JSON stays the default for them.
        if ($fromBrowser && $quality[self::JSON] > 0) {
            return self::JSON;
        }

        return $quality[self::XML] > $quality[self::JSON] ? self::XML : self::JSON;
    }

    /**
     * @return array{0: string, 1: float}
     */
    private function parse(string $part): array
    {
        $segments = array_map('trim', explode(';', strtolower($part)));
        $type = array_shift($segments);
        $q = 1.0;

        foreach ($segments as $segment) {
            if (str_starts_with($segment, 'q=')) {
                $q = (float) substr($segment, 2);
            }
        }

        return [$type, $q];
    }
}
