<?php

namespace Masum\AiTranslator\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Masum\AiTranslator\Http\Requests\AutoTranslateRequest;
use Masum\AiTranslator\Http\Requests\StoreTranslationRequest;
use Masum\AiTranslator\Http\Requests\UpdateTranslationRequest;
use Masum\AiTranslator\Http\Resources\TranslationResource;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\TranslationService;

class TranslationController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected TranslationService $translationService
    ) {
    }

    /**
     * List translations with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $query = $request->input('query');
        $languageCode = $request->input('language');
        $group = $request->input('group');
        $activeOnly = $request->boolean('active_only', true);
        $perPage = $request->integer('per_page', 50);

        $translations = $this->translationService->search(
            $query,
            $languageCode,
            $group,
            $activeOnly,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => TranslationResource::collection($translations->items()),
            'meta' => [
                'current_page' => $translations->currentPage(),
                'last_page' => $translations->lastPage(),
                'per_page' => $translations->perPage(),
                'total' => $translations->total(),
            ],
        ]);
    }

    /**
     * Create a new translation.
     */
    public function store(StoreTranslationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Check if auto-translate is requested
        if ($request->boolean('auto_translate') && !empty($validated['target_languages'])) {
            $translations = $this->translationService->autoTranslate(
                key: $validated['key'],
                sourceValue: $validated['value'],
                sourceLang: $request->input('language_code'),
                targetLangs: $validated['target_languages'],
                group: $validated['group'] ?? null,
                userId: $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Translation created and auto-translated successfully.',
                'data' => TranslationResource::collection(array_values($translations)),
            ], 201);
        }

        // Create single translation
        $translation = Translation::create([
            'language_id' => $validated['language_id'],
            'group' => $validated['group'] ?? null,
            'key' => $validated['key'],
            'value' => $validated['value'],
            'is_active' => $validated['is_active'] ?? true,
            'translated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Translation created successfully.',
            'data' => new TranslationResource($translation->load('language')),
        ], 201);
    }

    /**
     * Get a specific translation.
     */
    public function show(int $id): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $translation = Translation::with('language')->find($id);

        if (!$translation) {
            return response()->json([
                'success' => false,
                'message' => 'Translation not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new TranslationResource($translation),
        ]);
    }

    /**
     * Update a translation.
     */
    public function update(UpdateTranslationRequest $request, int $id): JsonResponse
    {
        $translation = Translation::find($id);

        if (!$translation) {
            return response()->json([
                'success' => false,
                'message' => 'Translation not found.',
            ], 404);
        }

        $validated = $request->validated();
        $validated['translated_by_user_id'] = $request->user()?->id;
        $validated['is_auto_translated'] = false; // Mark as manually edited

        $translation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Translation updated successfully.',
            'data' => new TranslationResource($translation->fresh(['language'])),
        ]);
    }

    /**
     * Delete a translation.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.delete_translations', 'delete-translations'));

        $translation = Translation::find($id);

        if (!$translation) {
            return response()->json([
                'success' => false,
                'message' => 'Translation not found.',
            ], 404);
        }

        $translation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Translation deleted successfully.',
        ]);
    }

    /**
     * Get translation history.
     */
    public function history(int $id): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $translation = Translation::find($id);

        if (!$translation) {
            return response()->json([
                'success' => false,
                'message' => 'Translation not found.',
            ], 404);
        }

        $history = $translation->histories()
            ->with('changedBy')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Auto-translate using AI.
     */
    public function autoTranslate(AutoTranslateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Check if queueing is enabled and user didn't request sync processing
        $useQueue = config('ai-translator.queue.enabled', true)
            && !$request->boolean('sync', false);

        if ($useQueue) {
            try {
                // Dispatch translation job to queue
                $job = \Masum\AiTranslator\Jobs\TranslateJob::dispatch(
                    $validated['key'],
                    $validated['value'],
                    $validated['source_language'],
                    $validated['target_languages'],
                    $validated['group'] ?? null,
                    $request->user()?->id
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Translation queued successfully. Processing in background.',
                    'data' => [
                        'status' => 'queued',
                        'job_id' => $job->id ?? null,
                    ],
                ], 202); // 202 Accepted
            } catch (\Exception $e) {
                // If queueing fails, fall back to sync processing
                \Log::warning('Failed to queue translation, falling back to sync', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Synchronous processing (either requested or fallback)
        try {
            $translations = $this->translationService->autoTranslate(
                key: $validated['key'],
                sourceValue: $validated['value'],
                sourceLang: $validated['source_language'],
                targetLangs: $validated['target_languages'],
                group: $validated['group'] ?? null,
                userId: $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Auto-translation completed successfully.',
                'data' => TranslationResource::collection(array_values($translations)),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auto-translation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch translate multiple keys.
     */
    public function batchTranslate(Request $request): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.auto_translate', 'auto-translate'));

        $validated = $request->validate([
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.key' => ['required', 'string'],
            'translations.*.value' => ['required', 'string'],
            'translations.*.group' => ['nullable', 'string'],
            'source_language' => ['required', 'string', 'exists:languages,code'],
            'target_languages' => ['required', 'array', 'min:1'],
            'target_languages.*' => ['string', 'exists:languages,code'],
            'group' => ['nullable', 'string'],
        ]);

        // Check if queueing is enabled and batching is supported
        $useQueue = config('ai-translator.queue.enabled', true)
            && config('ai-translator.queue.batch_enabled', true)
            && !$request->boolean('sync', false);

        if ($useQueue && count($validated['translations']) > 1) {
            try {
                // Dispatch batch translation job
                $job = \Masum\AiTranslator\Jobs\BatchTranslateJob::dispatch(
                    $validated['translations'],
                    $validated['source_language'],
                    $validated['target_languages'],
                    $request->user()?->id,
                    ['group' => $validated['group'] ?? null]
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Batch translation queued successfully. Processing in background.',
                    'data' => [
                        'status' => 'queued',
                        'job_id' => $job->id ?? null,
                        'count' => count($validated['translations']),
                    ],
                ], 202); // 202 Accepted
            } catch (\Exception $e) {
                // If queueing fails, fall back to sync processing
                \Log::warning('Failed to queue batch translation, falling back to sync', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Synchronous processing (either requested or fallback)
        try {
            $keyValues = collect($validated['translations'])->pluck('value', 'key')->toArray();

            $results = $this->translationService->batchTranslate(
                keyValues: $keyValues,
                sourceLang: $validated['source_language'],
                targetLangs: $validated['target_languages'],
                group: $validated['group'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Batch translation completed successfully.',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch translation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available translation groups.
     */
    public function groups(): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $groups = $this->translationService->getAvailableGroups();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * Clear translation cache.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.manage_translations', 'manage-translations'));

        $key = $request->input('key');
        $locale = $request->input('locale');
        $group = $request->input('group');

        $this->translationService->clearCache($key, $locale, $group);

        return response()->json([
            'success' => true,
            'message' => 'Translation cache cleared successfully.',
        ]);
    }
}
