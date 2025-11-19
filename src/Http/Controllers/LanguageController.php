<?php

namespace Masum\AiTranslator\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Masum\AiTranslator\Http\Requests\StoreLanguageRequest;
use Masum\AiTranslator\Http\Resources\LanguageResource;
use Masum\AiTranslator\Models\Language;

class LanguageController extends Controller
{
    use AuthorizesRequests;
    /**
     * List all languages.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $query = Language::query();

        // Add translation counts for statistics
        if ($request->boolean('with_stats', false)) {
            $query->withCount([
                'translations',
                'translations as active_translations_count' => function ($q) {
                    $q->where('is_active', true);
                },
                'translations as auto_translated_count' => function ($q) {
                    $q->where('is_auto_translated', true);
                },
            ]);
        }

        // Filter by active status
        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->active();
        }

        $languages = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => LanguageResource::collection($languages),
        ]);
    }

    /**
     * Create a new language.
     */
    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $language = Language::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Language created successfully.',
            'data' => new LanguageResource($language),
        ], 201);
    }

    /**
     * Get a specific language.
     */
    public function show(string $code): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $language = Language::withCount([
            'translations',
            'translations as active_translations_count' => function ($q) {
                $q->where('is_active', true);
            },
            'translations as auto_translated_count' => function ($q) {
                $q->where('is_auto_translated', true);
            },
        ])->where('code', $code)->first();

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LanguageResource($language),
        ]);
    }

    /**
     * Update a language.
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.manage_languages', 'manage-languages'));

        $language = Language::where('code', $code)->first();

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'native_name' => ['sometimes', 'string', 'max:255'],
            'direction' => ['sometimes', 'in:ltr,rtl'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'region' => ['nullable', 'string', 'max:255'],
        ]);

        $language->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Language updated successfully.',
            'data' => new LanguageResource($language->fresh()),
        ]);
    }

    /**
     * Delete a language.
     */
    public function destroy(string $code): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.manage_languages', 'manage-languages'));

        $language = Language::where('code', $code)->first();

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found.',
            ], 404);
        }

        // Prevent deleting default language
        if ($language->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the default language.',
            ], 422);
        }

        $language->delete();

        return response()->json([
            'success' => true,
            'message' => 'Language deleted successfully.',
        ]);
    }

    /**
     * Toggle language active status.
     */
    public function toggle(string $code): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.manage_languages', 'manage-languages'));

        $language = Language::where('code', $code)->first();

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found.',
            ], 404);
        }

        if ($language->is_active) {
            $success = $language->deactivate();
            $message = $success ? 'Language deactivated successfully.' : 'Cannot deactivate the default language.';
        } else {
            $success = $language->activate();
            $message = 'Language activated successfully.';
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => new LanguageResource($language->fresh()),
        ]);
    }

    /**
     * Set language as default.
     */
    public function setDefault(string $code): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.manage_languages', 'manage-languages'));

        $language = Language::where('code', $code)->first();

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found.',
            ], 404);
        }

        $language->setAsDefault();

        return response()->json([
            'success' => true,
            'message' => 'Language set as default successfully.',
            'data' => new LanguageResource($language->fresh()),
        ]);
    }

    /**
     * Get country information for a language.
     */
    public function countryInfo(string $code): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $language = Language::getByCode($code);

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $language->getCountryInfo(),
        ]);
    }

    /**
     * Get all language-country mappings.
     */
    public function allCountries(): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $languages = Language::all();
        $mappings = [];

        foreach ($languages as $language) {
            $mappings[] = $language->getCountryInfo();
        }

        return response()->json([
            'success' => true,
            'data' => $mappings,
        ]);
    }
}
