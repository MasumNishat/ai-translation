<?php

namespace Masum\AiTranslator\Http\Controllers;

use Masum\AiTranslator\Services\MissingTranslationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class MissingTranslationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected MissingTranslationService $missingTranslationService
    ) {}

    /**
     * Get missing translations for a specific language
     *
     * @param string $languageCode
     * @param Request $request
     * @return JsonResponse
     */
    public function getMissing(string $languageCode, Request $request): JsonResponse
    {
        try {
            $this->authorize('view-translations');

            $validated = $request->validate([
                'group' => 'nullable|string',
            ]);

            $missing = $this->missingTranslationService->findMissing(
                $languageCode,
                $validated['group'] ?? null
            );

            $stats = $this->missingTranslationService->getCompletionStats(
                $languageCode,
                $validated['group'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'missing_translations' => $missing->toArray(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Language '{$languageCode}' not found.",
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Get missing translations failed', [
                'language' => $languageCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve missing translations: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate comprehensive missing translation report for all languages
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getReport(Request $request): JsonResponse
    {
        try {
            $this->authorize('view-translations');

            $validated = $request->validate([
                'group' => 'nullable|string',
            ]);

            $report = $this->missingTranslationService->generateReport(
                $validated['group'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Generate missing translation report failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get completion statistics for a language
     *
     * @param string $languageCode
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(string $languageCode, Request $request): JsonResponse
    {
        try {
            $this->authorize('view-translations');

            $validated = $request->validate([
                'group' => 'nullable|string',
            ]);

            $stats = $this->missingTranslationService->getCompletionStats(
                $languageCode,
                $validated['group'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Language '{$languageCode}' not found.",
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Get completion stats failed', [
                'language' => $languageCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get missing translations grouped by group for a language
     *
     * @param string $languageCode
     * @return JsonResponse
     */
    public function getMissingByGroup(string $languageCode): JsonResponse
    {
        try {
            $this->authorize('view-translations');

            $missingByGroup = $this->missingTranslationService->getMissingByGroup($languageCode);

            return response()->json([
                'success' => true,
                'data' => $missingByGroup,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Language '{$languageCode}' not found.",
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Get missing by group failed', [
                'language' => $languageCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve missing translations by group: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get languages that need the most attention (most missing translations)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLanguagesNeedingAttention(Request $request): JsonResponse
    {
        try {
            $this->authorize('view-translations');

            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            $languages = $this->missingTranslationService->getLanguagesNeedingAttention(
                $validated['limit'] ?? 5
            );

            return response()->json([
                'success' => true,
                'data' => $languages,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Get languages needing attention failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve languages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a specific key is missing in a language
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkKey(Request $request): JsonResponse
    {
        try {
            $this->authorize('view-translations');

            $validated = $request->validate([
                'key' => 'required|string',
                'language' => 'required|string|exists:languages,code',
            ]);

            $isMissing = $this->missingTranslationService->isKeyMissing(
                $validated['key'],
                $validated['language']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $validated['key'],
                    'language' => $validated['language'],
                    'is_missing' => $isMissing,
                ],
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Check key failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check key: ' . $e->getMessage(),
            ], 500);
        }
    }
}
