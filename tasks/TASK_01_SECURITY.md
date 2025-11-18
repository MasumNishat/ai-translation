# TASK 01: Security & Authorization Enhancement

**Phase:** 1 - Foundation & Security
**Priority:** P1 - Critical
**Estimated Time:** 20-30 hours
**Dependencies:** None
**Complexity:** High

---

## Overview

Enhance the security and authorization system to be production-ready with proper user authentication requirements and security best practices.

---

## Tasks

### P1-T01-S01: Fix Authorization System ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 6-8 hours
**Assigned To:** -

#### Context

Currently, the package allows guest access for testing purposes. This needs to be configurable so it can be locked down in production while remaining flexible for development/testing.

#### Current Code

```php
// src/Http/Requests/StoreLanguageRequest.php
public function authorize(): bool
{
    if (!$this->user()) {
        return true; // Allow guest access - NOT SECURE
    }
    return $this->user()->can(config('ai-translator.permissions.manage_languages'));
}
```

#### Implementation Steps

1. **Add Configuration Option**

```php
// config/ai-translator.php
'security' => [
    'public_api' => env('TRANSLATOR_PUBLIC_API', false),
    'require_authentication' => env('TRANSLATOR_REQUIRE_AUTH', true),
    'guest_permissions' => [
        'view_translations' => env('TRANSLATOR_GUEST_VIEW', false),
    ],
],
```

2. **Update Form Requests**

```php
// src/Http/Requests/StoreLanguageRequest.php
public function authorize(): bool
{
    // Check if public API mode is enabled
    if (config('ai-translator.security.public_api', false)) {
        return true;
    }

    // Require authentication in production
    if (!$this->user() && config('ai-translator.security.require_authentication', true)) {
        return false;
    }

    // Check specific permission
    return $this->user()?->can(
        config('ai-translator.permissions.manage_languages', 'manage-languages')
    ) ?? false;
}
```

3. **Files to Update**

- [ ] `src/Http/Requests/StoreLanguageRequest.php`
- [ ] `src/Http/Requests/StoreTranslationRequest.php`
- [ ] `src/Http/Requests/UpdateTranslationRequest.php`
- [ ] `src/Http/Requests/AutoTranslateRequest.php`
- [ ] `config/ai-translator.php`

4. **Add Tests**

```php
// tests/Feature/AuthorizationTest.php
test('guests cannot create languages when auth is required', function () {
    config(['ai-translator.security.require_authentication' => true]);

    $response = $this->postJson('/api/translator/languages', [
        'code' => 'de',
        'name' => 'German',
        // ...
    ]);

    $response->assertStatus(403);
});

test('guests can create languages when public API is enabled', function () {
    config(['ai-translator.security.public_api' => true]);

    $response = $this->postJson('/api/translator/languages', [
        'code' => 'de',
        'name' => 'German',
        // ...
    ]);

    $response->assertStatus(201);
});
```

#### Acceptance Criteria

- [ ] Public API mode can be toggled via config
- [ ] Production defaults to requiring authentication
- [ ] Guest permissions are granular and configurable
- [ ] All form requests use consistent authorization logic
- [ ] Tests cover all authorization scenarios
- [ ] Documentation updated with security best practices

#### Testing Checklist

- [ ] Test with public API mode enabled
- [ ] Test with authentication required
- [ ] Test with authenticated user
- [ ] Test with unauthenticated user
- [ ] Test with user without permissions
- [ ] Test with user with permissions

---

### P1-T01-S02: Add Rate Limiting ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 4-6 hours
**Assigned To:** -

#### Context

AI translation endpoints are expensive and should be rate-limited to prevent abuse and control costs.

#### Implementation Steps

1. **Create Rate Limiter Configuration**

```php
// config/ai-translator.php
'rate_limiting' => [
    'enabled' => env('TRANSLATOR_RATE_LIMIT_ENABLED', true),
    'auto_translate' => [
        'max_attempts' => env('TRANSLATOR_AUTO_TRANSLATE_MAX_ATTEMPTS', 10),
        'decay_minutes' => env('TRANSLATOR_AUTO_TRANSLATE_DECAY', 1),
    ],
    'batch_translate' => [
        'max_attempts' => env('TRANSLATOR_BATCH_TRANSLATE_MAX_ATTEMPTS', 5),
        'decay_minutes' => env('TRANSLATOR_BATCH_TRANSLATE_DECAY', 1),
    ],
    'api' => [
        'max_attempts' => env('TRANSLATOR_API_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('TRANSLATOR_API_DECAY', 1),
    ],
],
```

2. **Register Rate Limiters**

```php
// src/AiTranslatorServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    $this->configureRateLimiting();
}

protected function configureRateLimiting(): void
{
    if (!config('ai-translator.rate_limiting.enabled', true)) {
        return;
    }

    RateLimiter::for('translator-auto-translate', function (Request $request) {
        return Limit::perMinute(
            config('ai-translator.rate_limiting.auto_translate.max_attempts', 10)
        )->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('translator-batch-translate', function (Request $request) {
        return Limit::perMinute(
            config('ai-translator.rate_limiting.batch_translate.max_attempts', 5)
        )->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('translator-api', function (Request $request) {
        return Limit::perMinute(
            config('ai-translator.rate_limiting.api.max_attempts', 60)
        )->by($request->user()?->id ?: $request->ip());
    });
}
```

3. **Apply to Routes**

```php
// routes/api.php
Route::middleware(['throttle:translator-api'])->group(function () {
    Route::get('/languages', [LanguageController::class, 'index']);
    Route::get('/translations', [TranslationController::class, 'index']);
    // ... other read operations
});

Route::middleware(['throttle:translator-auto-translate'])->group(function () {
    Route::post('/auto-translate', [TranslationController::class, 'autoTranslate']);
});

Route::middleware(['throttle:translator-batch-translate'])->group(function () {
    Route::post('/batch-translate', [TranslationController::class, 'batchTranslate']);
});
```

4. **Add Custom Response**

```php
// src/Http/Middleware/ThrottleTranslations.php
namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ThrottleTranslations
{
    public function handle(Request $request, Closure $next, string $limiter = 'translator-api')
    {
        $key = $request->user()?->id ?: $request->ip();

        if (RateLimiter::tooManyAttempts($limiter . ':' . $key, 1)) {
            $seconds = RateLimiter::availableIn($limiter . ':' . $key);

            return response()->json([
                'success' => false,
                'message' => 'Too many translation requests. Please try again later.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($limiter . ':' . $key);

        return $next($request);
    }
}
```

#### Acceptance Criteria

- [ ] Rate limiting configurable via environment
- [ ] Different limits for different endpoints
- [ ] Rate limiting by user ID or IP
- [ ] Clear error messages when limit exceeded
- [ ] Retry-After header included in response
- [ ] Rate limiting can be disabled for testing

#### Testing Checklist

- [ ] Test rate limit enforcement
- [ ] Test with authenticated user
- [ ] Test with unauthenticated user (IP-based)
- [ ] Test retry after cooldown
- [ ] Test different limits for different endpoints
- [ ] Test with rate limiting disabled

---

### P1-T01-S03: Encrypt Sensitive Settings ⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 4-5 hours
**Assigned To:** -

#### Context

The Gemini API key and other sensitive settings are stored in plain text in the database. This should be encrypted.

#### Implementation Steps

1. **Update PackageSetting Model**

```php
// src/Models/PackageSetting.php
namespace Masum\AiTranslator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PackageSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'is_encrypted', 'description'];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    // Automatically encrypt/decrypt sensitive values
    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                \Log::error('Failed to decrypt setting: ' . $this->key);
                return null;
            }
        }

        return $value;
    }

    public function setValueAttribute($value)
    {
        if ($this->shouldEncrypt()) {
            $this->attributes['value'] = Crypt::encryptString($value);
            $this->attributes['is_encrypted'] = true;
        } else {
            $this->attributes['value'] = $value;
            $this->attributes['is_encrypted'] = false;
        }
    }

    protected function shouldEncrypt(): bool
    {
        $sensitiveKeys = config('ai-translator.security.encrypted_settings', [
            'gemini_api_key',
            'api_key',
            'api_secret',
            'password',
        ]);

        return in_array($this->key, $sensitiveKeys);
    }

    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting?->value ?? $default;
    }

    public static function set(string $key, $value, string $type = 'string', ?string $description = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
            ]
        );
    }
}
```

2. **Add Configuration**

```php
// config/ai-translator.php
'security' => [
    'encrypted_settings' => [
        'gemini_api_key',
        'api_key',
        'api_secret',
        'api_token',
        'password',
        'secret',
    ],
],
```

3. **Migration for is_encrypted Column**

```php
// database/migrations/xxxx_add_is_encrypted_to_package_settings.php
public function up(): void
{
    Schema::table('package_settings', function (Blueprint $table) {
        $table->boolean('is_encrypted')->default(false)->after('type');
    });
}
```

4. **Add Artisan Command to Encrypt Existing**

```php
// src/Console/Commands/EncryptSettingsCommand.php
namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\PackageSetting;

class EncryptSettingsCommand extends Command
{
    protected $signature = 'translator:encrypt-settings {--force}';
    protected $description = 'Encrypt sensitive settings in database';

    public function handle()
    {
        $sensitiveKeys = config('ai-translator.security.encrypted_settings', []);

        $this->info('Encrypting sensitive settings...');

        foreach ($sensitiveKeys as $key) {
            $setting = PackageSetting::where('key', $key)->first();

            if ($setting && !$setting->is_encrypted) {
                $plainValue = $setting->getAttributes()['value'];
                $setting->value = $plainValue; // Will trigger encryption
                $setting->save();

                $this->info("✓ Encrypted: {$key}");
            }
        }

        $this->info('Encryption complete!');
    }
}
```

#### Acceptance Criteria

- [ ] Sensitive settings automatically encrypted on save
- [ ] Automatic decryption when reading
- [ ] Configurable list of sensitive keys
- [ ] Migration command for existing data
- [ ] Graceful handling of decryption failures
- [ ] Tests for encryption/decryption

#### Testing Checklist

- [ ] Test automatic encryption on create
- [ ] Test automatic decryption on read
- [ ] Test update of encrypted value
- [ ] Test decryption failure handling
- [ ] Test migration command
- [ ] Test with different sensitive keys

---

### P1-T01-S04: Add Input Sanitization ⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 3-4 hours
**Assigned To:** -

#### Context

User inputs, especially translation values, should be sanitized to prevent XSS and other injection attacks.

#### Implementation Steps

1. **Create Sanitizer Service**

```php
// src/Services/TranslationSanitizer.php
namespace Masum\AiTranslator\Services;

class TranslationSanitizer
{
    protected array $allowedTags;
    protected bool $allowHtml;

    public function __construct()
    {
        $this->allowHtml = config('ai-translator.security.allow_html', false);
        $this->allowedTags = config('ai-translator.security.allowed_html_tags', []);
    }

    public function sanitize(string $value, array $options = []): string
    {
        // Merge options with defaults
        $allowHtml = $options['allow_html'] ?? $this->allowHtml;
        $allowedTags = $options['allowed_tags'] ?? $this->allowedTags;

        // Strip dangerous tags if HTML not allowed
        if (!$allowHtml) {
            $value = strip_tags($value);
        } elseif (!empty($allowedTags)) {
            $value = strip_tags($value, '<' . implode('><', $allowedTags) . '>');
        }

        // Prevent XSS
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);

        // Trim whitespace
        $value = trim($value);

        // Remove NULL bytes
        $value = str_replace(chr(0), '', $value);

        return $value;
    }

    public function sanitizeKey(string $key): string
    {
        // Only allow alphanumeric, dots, underscores, and hyphens
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $key);
    }

    public function sanitizeGroup(?string $group): ?string
    {
        if ($group === null) {
            return null;
        }

        // Only allow alphanumeric, underscores, and hyphens
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $group);
    }
}
```

2. **Update Configuration**

```php
// config/ai-translator.php
'security' => [
    'sanitize_input' => env('TRANSLATOR_SANITIZE_INPUT', true),
    'allow_html' => env('TRANSLATOR_ALLOW_HTML', false),
    'allowed_html_tags' => ['b', 'i', 'u', 'strong', 'em', 'br'],
],
```

3. **Apply in Form Requests**

```php
// src/Http/Requests/StoreTranslationRequest.php
use Masum\AiTranslator\Services\TranslationSanitizer;

protected function prepareForValidation(): void
{
    if (!config('ai-translator.security.sanitize_input', true)) {
        return;
    }

    $sanitizer = app(TranslationSanitizer::class);

    $this->merge([
        'key' => $sanitizer->sanitizeKey($this->key),
        'value' => $sanitizer->sanitize($this->value),
        'group' => $sanitizer->sanitizeGroup($this->group),
    ]);
}
```

4. **Add Service Provider Binding**

```php
// src/AiTranslatorServiceProvider.php
public function register(): void
{
    $this->app->singleton(TranslationSanitizer::class);
}
```

#### Acceptance Criteria

- [ ] All user inputs sanitized by default
- [ ] HTML can be optionally allowed
- [ ] Allowed HTML tags configurable
- [ ] XSS prevention in place
- [ ] Keys and groups validated for safe characters
- [ ] Sanitization can be disabled for testing

#### Testing Checklist

- [ ] Test XSS attack prevention
- [ ] Test with HTML input when disabled
- [ ] Test with allowed HTML tags
- [ ] Test key sanitization
- [ ] Test group sanitization
- [ ] Test with sanitization disabled

---

### P1-T01-S05: Add CSRF Protection Configuration ⭐

**Status:** 🔴 Not Started
**Time Estimate:** 2-3 hours
**Assigned To:** -

#### Context

Add configuration for CSRF protection on API routes for stateful applications.

#### Implementation Steps

1. **Add Configuration**

```php
// config/ai-translator.php
'security' => [
    'csrf_protection' => env('TRANSLATOR_CSRF_PROTECTION', false),
    'stateful_domains' => array_filter(
        explode(',', env('TRANSLATOR_STATEFUL_DOMAINS', ''))
    ),
],
```

2. **Create Middleware**

```php
// src/Http/Middleware/VerifyCsrfToken.php
namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('ai-translator.security.csrf_protection', false)) {
            return $next($request);
        }

        if ($this->isStatefulDomain($request)) {
            if (!$this->tokensMatch($request)) {
                throw new TokenMismatchException('CSRF token mismatch.');
            }
        }

        return $next($request);
    }

    protected function isStatefulDomain(Request $request): bool
    {
        $statefulDomains = config('ai-translator.security.stateful_domains', []);
        $host = $request->getHost();

        foreach ($statefulDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    protected function tokensMatch(Request $request): bool
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');
        return hash_equals((string) $request->session()->token(), (string) $token);
    }
}
```

3. **Apply Middleware (Optional)**

```php
// routes/api.php (example for stateful apps)
Route::middleware(['translator.csrf'])->group(function () {
    Route::post('/translations', [TranslationController::class, 'store']);
    Route::put('/translations/{id}', [TranslationController::class, 'update']);
    // ...
});
```

#### Acceptance Criteria

- [ ] CSRF protection can be enabled/disabled
- [ ] Stateful domains configurable
- [ ] Token validation works correctly
- [ ] Clear error messages on mismatch
- [ ] Documentation for stateful apps

---

## Summary

### Total Subtasks: 5
### Estimated Time: 20-30 hours
### Priority: P1 - Critical

### Completion Checklist

- [ ] All authorization logic updated
- [ ] Rate limiting implemented
- [ ] Sensitive data encrypted
- [ ] Input sanitization in place
- [ ] CSRF protection configured
- [ ] All tests passing
- [ ] Documentation updated
- [ ] Security audit completed

### Next Steps

After completing this task group:
1. Move to TASK_02_PERFORMANCE.md
2. Review security with team
3. Update CHANGELOG.md
4. Tag release as v1.1.0
