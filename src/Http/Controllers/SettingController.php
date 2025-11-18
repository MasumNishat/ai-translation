<?php

namespace Masum\AiTranslator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Masum\AiTranslator\Models\PackageSetting;

class SettingController extends Controller
{
    /**
     * Get all settings.
     */
    public function index(): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $settings = PackageSetting::all()->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->is_encrypted ? '***HIDDEN***' : $setting->getValue(),
                'type' => $setting->type,
                'is_encrypted' => $setting->is_encrypted,
                'description' => $setting->description,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Get a specific setting.
     */
    public function show(string $key): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.view_translations', 'view-translations'));

        $setting = PackageSetting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
            ], 404);
        }

        $value = $setting->is_encrypted ? '***HIDDEN***' : $setting->getValue();

        return response()->json([
            'success' => true,
            'data' => [
                'key' => $setting->key,
                'value' => $value,
                'type' => $setting->type,
                'is_encrypted' => $setting->is_encrypted,
                'description' => $setting->description,
            ],
        ]);
    }

    /**
     * Update a setting.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.manage_settings', 'manage-translator-settings'));

        $validated = $request->validate([
            'value' => ['required'],
            'type' => ['sometimes', 'in:string,integer,boolean,json,array'],
            'is_encrypted' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'string'],
        ]);

        // Determine if this should be encrypted
        $shouldEncrypt = $validated['is_encrypted'] ?? $this->shouldEncryptKey($key);

        // Determine type based on key if not provided
        $type = $validated['type'] ?? $this->determineType($key, $validated['value']);

        $setting = PackageSetting::set(
            key: $key,
            value: $validated['value'],
            type: $type,
            encrypt: $shouldEncrypt,
            description: $validated['description'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully.',
            'data' => [
                'key' => $setting->key,
                'value' => $setting->is_encrypted ? '***HIDDEN***' : $setting->getValue(),
                'type' => $setting->type,
                'is_encrypted' => $setting->is_encrypted,
            ],
        ]);
    }

    /**
     * Delete a setting.
     */
    public function destroy(string $key): JsonResponse
    {
        $this->authorize(config('ai-translator.permissions.manage_settings', 'manage-translator-settings'));

        $removed = PackageSetting::remove($key);

        if (!$removed) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting deleted successfully.',
        ]);
    }

    /**
     * Determine if a key should be encrypted.
     */
    protected function shouldEncryptKey(string $key): bool
    {
        $sensitiveKeys = [
            'gemini_api_key',
            'api_key',
            'secret',
            'password',
            'token',
        ];

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains(strtolower($key), $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine the type of a value.
     */
    protected function determineType(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_string($value) && $this->isJson($value)) {
            return 'json';
        }

        return 'string';
    }

    /**
     * Check if a string is valid JSON.
     */
    protected function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
