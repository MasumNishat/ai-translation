<?php

namespace Masum\AiTranslator\Http\Controllers;

use Masum\AiTranslator\Services\JsonImportExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class ImportExportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected JsonImportExportService $importExportService
    ) {}

    /**
     * Export translations for a language to JSON
     *
     * @param string $languageCode
     * @return JsonResponse
     */
    public function exportJson(string $languageCode): JsonResponse
    {
        try {
            $this->authorize('export-translations');

            $data = $this->importExportService->export($languageCode);

            return response()->json($data)
                ->header('Content-Disposition', "attachment; filename=\"translations-{$languageCode}.json\"");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Language '{$languageCode}' not found.",
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to export translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Export failed', [
                'language' => $languageCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export translations for a specific group
     *
     * @param string $languageCode
     * @param string $group
     * @return JsonResponse
     */
    public function exportJsonByGroup(string $languageCode, string $group): JsonResponse
    {
        try {
            $this->authorize('export-translations');

            $data = $this->importExportService->export($languageCode, $group);

            return response()->json($data)
                ->header('Content-Disposition', "attachment; filename=\"translations-{$languageCode}-{$group}.json\"");
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Language '{$languageCode}' not found.",
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to export translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Export by group failed', [
                'language' => $languageCode,
                'group' => $group,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export all active languages
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function exportAll(Request $request): JsonResponse
    {
        try {
            $this->authorize('export-translations');

            $validated = $request->validate([
                'languages' => 'nullable|array',
                'languages.*' => 'string|exists:languages,code',
                'group' => 'nullable|string',
            ]);

            $data = $this->importExportService->exportAll(
                $validated['languages'] ?? null,
                $validated['group'] ?? null
            );

            $filename = 'translations-all-' . now()->format('Y-m-d-His') . '.json';

            return response()->json($data)
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to export translations.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Export all failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import translations from JSON
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function importJson(Request $request): JsonResponse
    {
        try {
            $this->authorize('import-translations');

            $validated = $request->validate([
                'file' => 'required|file|mimes:json,txt|max:10240', // 10MB max
                'overwrite' => 'boolean',
                'create_language' => 'boolean',
            ]);

            $contents = file_get_contents($validated['file']->path());
            $data = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON file: ' . json_last_error_msg(),
                ], 422);
            }

            $stats = $this->importExportService->import($data, [
                'overwrite' => $validated['overwrite'] ?? true,
                'create_language' => $validated['create_language'] ?? false,
            ]);

            $hasErrors = !empty($stats['errors']);

            return response()->json([
                'success' => !$hasErrors || $stats['created'] > 0 || $stats['updated'] > 0,
                'message' => $this->generateImportMessage($stats),
                'data' => $stats,
            ], $hasErrors && $stats['created'] === 0 && $stats['updated'] === 0 ? 422 : 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to import translations.',
            ], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate human-readable import message
     *
     * @param array $stats
     * @return string
     */
    protected function generateImportMessage(array $stats): string
    {
        $parts = [];

        if ($stats['created'] > 0) {
            $parts[] = "{$stats['created']} created";
        }

        if ($stats['updated'] > 0) {
            $parts[] = "{$stats['updated']} updated";
        }

        if ($stats['skipped'] > 0) {
            $parts[] = "{$stats['skipped']} skipped";
        }

        if (!empty($stats['errors'])) {
            $parts[] = count($stats['errors']) . " errors";
        }

        if (empty($parts)) {
            return 'No translations imported.';
        }

        return 'Import completed: ' . implode(', ', $parts) . '.';
    }
}
