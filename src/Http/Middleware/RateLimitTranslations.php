<?php

namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitTranslations
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $limiter = 'translations'): Response
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts($limiter))) {
            return $this->buildResponse($key, $limiter);
        }

        RateLimiter::hit($key, $this->decaySeconds($limiter));

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $limiter,
            $this->calculateRemainingAttempts($key, $limiter)
        );
    }

    /**
     * Resolve request signature
     */
    protected function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return sha1('translator_' . $user->id . '|' . $request->ip());
        }

        return sha1('translator_guest|' . $request->ip());
    }

    /**
     * Get max attempts for limiter
     */
    protected function maxAttempts(string $limiter): int
    {
        return config("ai-translator.rate_limiting.{$limiter}.max_attempts", 60);
    }

    /**
     * Get decay seconds for limiter
     */
    protected function decaySeconds(string $limiter): int
    {
        return config("ai-translator.rate_limiting.{$limiter}.decay_seconds", 60);
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildResponse(string $key, string $limiter): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retryAfter,
        ], 429, [
            'X-RateLimit-Limit' => $this->maxAttempts($limiter),
            'X-RateLimit-Remaining' => 0,
            'Retry-After' => $retryAfter,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);
    }

    /**
     * Calculate remaining attempts
     */
    protected function calculateRemainingAttempts(string $key, string $limiter): int
    {
        return RateLimiter::remaining($key, $this->maxAttempts($limiter));
    }

    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, string $limiter, int $remainingAttempts): Response
    {
        $response->headers->set('X-RateLimit-Limit', $this->maxAttempts($limiter));
        $response->headers->set('X-RateLimit-Remaining', $remainingAttempts);

        return $response;
    }
}
