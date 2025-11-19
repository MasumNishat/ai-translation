# TASK 11: Documentation & Examples

**Priority:** P2 (High)
**Total Estimated Time:** 10-14 hours
**Dependencies:** All previous tasks
**Status:** ⏳ Pending

---

## Overview

Create comprehensive documentation including API documentation, usage guides, code examples, video tutorials, and migration guides.

---

## Subtasks

### P2-T11-S01: API Documentation (OpenAPI/Swagger)

**Estimated Time:** 3-4 hours
**Priority:** P2
**Dependencies:** None

#### Description
Create comprehensive API documentation using OpenAPI 3.0 specification with interactive Swagger UI.

#### Implementation

**1. Install Dependencies**

```bash
composer require darkaonline/l5-swagger
```

**2. Add OpenAPI Annotations to Controllers**

```php
<?php

namespace Masum\AiTranslator\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Laravel AI Translator API",
 *     description="Comprehensive translation management API with AI integration",
 *     @OA\Contact(
 *         email="support@example.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api/translator",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class LanguageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/languages",
     *     summary="Get all languages",
     *     description="Retrieve list of all available languages",
     *     operationId="getLanguages",
     *     tags={"Languages"},
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter active languages only",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Language")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Implementation
    }

    /**
     * @OA\Post(
     *     path="/languages",
     *     summary="Create new language",
     *     description="Create a new language in the system",
     *     operationId="createLanguage",
     *     tags={"Languages"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateLanguageRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Language created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Language")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function store(StoreLanguageRequest $request)
    {
        // Implementation
    }
}
```

**3. Add Schema Definitions**

```php
<?php

namespace Masum\AiTranslator\Models;

/**
 * @OA\Schema(
 *     schema="Language",
 *     type="object",
 *     title="Language",
 *     description="Language model",
 *     required={"id", "code", "name", "native_name", "direction"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="en"),
 *     @OA\Property(property="name", type="string", example="English"),
 *     @OA\Property(property="native_name", type="string", example="English"),
 *     @OA\Property(property="direction", type="string", enum={"ltr", "rtl"}, example="ltr"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_default", type="boolean", example=false),
 *     @OA\Property(property="country_code", type="string", example="US"),
 *     @OA\Property(property="region", type="string", example="North America"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="CreateLanguageRequest",
 *     type="object",
 *     required={"code", "name", "native_name", "direction"},
 *     @OA\Property(property="code", type="string", example="es"),
 *     @OA\Property(property="name", type="string", example="Spanish"),
 *     @OA\Property(property="native_name", type="string", example="Español"),
 *     @OA\Property(property="direction", type="string", enum={"ltr", "rtl"}, example="ltr"),
 *     @OA\Property(property="country_code", type="string", example="ES"),
 *     @OA\Property(property="region", type="string", example="Europe")
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string")
 *         )
 *     )
 * )
 */
class Language extends Model
{
    // Model implementation
}
```

**4. Generate Documentation**

```bash
php artisan l5-swagger:generate
```

**5. Access Swagger UI**

```
http://your-app/api/documentation
```

#### Acceptance Criteria
- [ ] All API endpoints documented
- [ ] Request/response schemas defined
- [ ] Interactive Swagger UI available
- [ ] Authentication documented
- [ ] Error responses documented
- [ ] Code examples included
- [ ] Downloadable OpenAPI spec

---

### P2-T11-S02: Usage Guides & Tutorials

**Estimated Time:** 3-4 hours
**Priority:** P2
**Dependencies:** None

#### Description
Create comprehensive usage guides and tutorials for different use cases.

#### Implementation

**1. Quick Start Guide**

```markdown
# guides/QUICK_START.md

# Quick Start Guide

Get up and running with Laravel AI Translator in 5 minutes.

## Installation

```bash
composer require masum/laravel-ai-translator
php artisan vendor:publish --tag=ai-translator-config
php artisan vendor:publish --tag=ai-translator-migrations
php artisan migrate
```

## Configuration

Add your Gemini API key to `.env`:

```env
GEMINI_API_KEY=your_api_key_here
```

## Basic Usage

### 1. Create a Language

```php
use Masum\AiTranslator\Models\Language;

$language = Language::create([
    'code' => 'en',
    'name' => 'English',
    'native_name' => 'English',
    'direction' => 'ltr',
    'is_default' => true,
]);
```

### 2. Create a Translation

```php
use Masum\AiTranslator\Models\Translation;

$translation = Translation::create([
    'key' => 'welcome.message',
    'value' => 'Welcome to our application!',
    'language_id' => $language->id,
    'group' => 'home',
]);
```

### 3. Retrieve Translation

```php
// Using helper
echo ai_trans('welcome.message'); // Welcome to our application!

// Using facade
use Masum\AiTranslator\Facades\AiTranslator;

echo AiTranslator::get('welcome.message', 'en');
```

### 4. Auto-Translate to Multiple Languages

```php
use Masum\AiTranslator\Services\TranslationService;

$service = app(TranslationService::class);

$service->autoTranslate(
    key: 'welcome.message',
    sourceValue: 'Welcome!',
    sourceLanguage: 'en',
    targetLanguages: ['es', 'fr', 'de']
);
```

## Next Steps

- [Complete Documentation](../README.md)
- [API Reference](./API_REFERENCE.md)
- [Advanced Features](./ADVANCED_USAGE.md)
```

**2. Advanced Usage Guide**

```markdown
# guides/ADVANCED_USAGE.md

# Advanced Usage Guide

## Custom Translation Providers

Create custom AI translation providers:

```php
use Masum\AiTranslator\Contracts\TranslationProvider;

class CustomProvider implements TranslationProvider
{
    public function translate(
        string $text,
        string $sourceLanguage,
        array $targetLanguages
    ): array {
        // Your implementation
    }
}

// Register in config
'providers' => [
    'custom' => CustomProvider::class,
],
```

## Caching Strategies

### Cache Tags

```php
use Illuminate\Support\Facades\Cache;

// Clear all translations for a language
Cache::tags(['translations', 'lang:en'])->flush();

// Clear specific group
Cache::tags(['translations', 'group:auth'])->flush();
```

### Custom Cache Driver

```php
// config/ai-translator.php
'cache' => [
    'driver' => 'redis', // or 'memcached', 'database'
    'ttl' => 3600,
    'prefix' => 'ai_trans_',
],
```

## Events and Listeners

### Listen to Translation Events

```php
use Masum\AiTranslator\Events\TranslationCreated;

Event::listen(TranslationCreated::class, function ($event) {
    // Send notification
    // Update search index
    // Clear CDN cache
});
```

## More Topics...
```

**3. Migration Guide**

```markdown
# guides/MIGRATION_GUIDE.md

# Migration Guide

## From Laravel's Built-in Translations

### Step 1: Export Existing Translations

```php
php artisan translator:import resources/lang/en.json --format=json
```

### Step 2: Update Code

Replace:
```php
__('messages.welcome')
trans('auth.failed')
```

With:
```php
ai_trans('messages.welcome')
ai_trans('auth.failed')
```

## From Other Packages

### From spatie/laravel-translation-loader

...
```

**4. Troubleshooting Guide**

```markdown
# guides/TROUBLESHOOTING.md

# Troubleshooting Guide

## Common Issues

### Issue: Translations not updating

**Solution:**
```bash
php artisan translator:clear-cache
php artisan config:clear
```

### Issue: AI translation not working

**Symptoms:** Auto-translate returns empty or error

**Possible causes:**
1. Invalid Gemini API key
2. Rate limiting
3. Network issues

**Solutions:**
```bash
# Verify API key
php artisan tinker
>>> config('ai-translator.gemini.api_key')

# Test API connection
php artisan translator:test-ai
```

## More issues...
```

#### Acceptance Criteria
- [ ] Quick start guide complete
- [ ] Advanced usage guide created
- [ ] Migration guides for common scenarios
- [ ] Troubleshooting guide comprehensive
- [ ] Code examples work correctly
- [ ] Guides are well-organized
- [ ] Search functionality (if docs site)

---

### P2-T11-S03: Code Examples Repository

**Estimated Time:** 2-3 hours
**Priority:** P3
**Dependencies:** None

#### Description
Create repository of working code examples for common use cases.

#### Implementation

**1. Create Examples Directory**

```
examples/
├── basic-usage/
│   ├── create-translation.php
│   ├── retrieve-translation.php
│   └── update-translation.php
├── advanced/
│   ├── custom-provider.php
│   ├── event-listeners.php
│   └── caching-strategies.php
├── api-integration/
│   ├── react-example.jsx
│   ├── vue-example.vue
│   └── javascript-example.js
└── blade-examples/
    ├── language-switcher.blade.php
    ├── translated-forms.blade.php
    └── rtl-support.blade.php
```

**2. Example: React Integration**

```jsx
// examples/api-integration/react-example.jsx

import React, { useState, useEffect } from 'react';

function TranslationManager() {
    const [languages, setLanguages] = useState([]);
    const [translations, setTranslations] = useState([]);
    const [currentLang, setCurrentLang] = useState('en');

    useEffect(() => {
        fetchLanguages();
        fetchTranslations(currentLang);
    }, [currentLang]);

    const fetchLanguages = async () => {
        const response = await fetch('/api/translator/languages');
        const data = await response.json();
        setLanguages(data.data);
    };

    const fetchTranslations = async (lang) => {
        const response = await fetch(`/api/translator/translations?language=${lang}`);
        const data = await response.json();
        setTranslations(data.data);
    };

    const switchLanguage = (lang) => {
        setCurrentLang(lang);
        localStorage.setItem('language', lang);
    };

    return (
        <div>
            <LanguageSwitcher
                languages={languages}
                current={currentLang}
                onChange={switchLanguage}
            />
            <TranslationList translations={translations} />
        </div>
    );
}
```

**3. Example: Custom Blade Directive**

```blade
{{-- examples/blade-examples/language-switcher.blade.php --}}

<div class="language-switcher">
    @languages($language)
        <a href="{{ route('set-language', $language->code) }}"
           class="@if(app()->getLocale() === $language->code) active @endif">
            {{ $language->native_name }}
        </a>
    @endlanguages
</div>

<style>
@rtl
    .language-switcher {
        direction: rtl;
        text-align: right;
    }
@endrtl

@ltr
    .language-switcher {
        direction: ltr;
        text-align: left;
    }
@endltr
</style>
```

#### Acceptance Criteria
- [ ] Examples for all major features
- [ ] Examples are tested and working
- [ ] Frontend integration examples
- [ ] Blade template examples
- [ ] API client examples
- [ ] README for each example

---

### P2-T11-S04: Video Tutorials (Optional)

**Estimated Time:** 2-3 hours (planning/scripting)
**Priority:** P4
**Dependencies:** All documentation complete

#### Description
Create video tutorial series for visual learners.

#### Implementation

**1. Tutorial Topics**

1. Getting Started (5 min)
   - Installation
   - Configuration
   - First translation

2. Working with the API (10 min)
   - Authentication
   - CRUD operations
   - Auto-translation

3. Frontend Integration (8 min)
   - React example
   - Vue example
   - Language switching

4. Advanced Features (12 min)
   - Custom providers
   - Events and webhooks
   - Analytics

**2. Create Accompanying Resources**

```markdown
# videos/01-getting-started/README.md

# Video 1: Getting Started

## What You'll Learn
- Install Laravel AI Translator
- Configure Gemini API
- Create your first translation

## Resources
- [Slide Deck](./slides.pdf)
- [Code Examples](./examples/)
- [Cheat Sheet](./cheat-sheet.md)

## Timestamps
- 0:00 Introduction
- 0:30 Installation
- 2:00 Configuration
- 3:30 First Translation
- 4:45 Recap
```

#### Acceptance Criteria
- [ ] Tutorial scripts written
- [ ] Code examples prepared
- [ ] Accompanying resources created
- [ ] Hosting platform selected (YouTube, Vimeo)
- [ ] Video descriptions optimized for SEO

---

### P2-T11-S05: API Client Libraries Documentation

**Estimated Time:** 1-2 hours
**Priority:** P3
**Dependencies:** P2-T11-S01

#### Description
Document how to use the API with popular HTTP clients and create SDK documentation.

#### Implementation

**1. cURL Examples**

```markdown
# docs/API_CLIENTS.md

## cURL Examples

### Get All Languages

```bash
curl -X GET \
  'https://your-app.com/api/translator/languages' \
  -H 'Accept: application/json'
```

### Create Translation

```bash
curl -X POST \
  'https://your-app.com/api/translator/translations' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -d '{
    "key": "welcome.message",
    "value": "Welcome!",
    "language_code": "en",
    "group": "home"
  }'
```

## JavaScript/Axios

```javascript
// Install: npm install axios

const axios = require('axios');

const client = axios.create({
    baseURL: 'https://your-app.com/api/translator',
    headers: {
        'Authorization': 'Bearer YOUR_TOKEN',
        'Content-Type': 'application/json'
    }
});

// Get languages
const languages = await client.get('/languages');

// Create translation
const translation = await client.post('/translations', {
    key: 'welcome.message',
    value: 'Welcome!',
    language_code: 'en',
    group: 'home'
});
```

## Python Requests

```python
import requests

BASE_URL = 'https://your-app.com/api/translator'
headers = {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
}

# Get languages
response = requests.get(f'{BASE_URL}/languages', headers=headers)
languages = response.json()

# Create translation
data = {
    'key': 'welcome.message',
    'value': 'Welcome!',
    'language_code': 'en',
    'group': 'home'
}
response = requests.post(f'{BASE_URL}/translations', json=data, headers=headers)
```
```

#### Acceptance Criteria
- [ ] Examples for popular languages/frameworks
- [ ] Authentication examples
- [ ] Error handling examples
- [ ] Pagination examples
- [ ] All examples tested

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] API documentation complete and accessible
- [ ] Usage guides comprehensive
- [ ] Code examples work correctly
- [ ] Documentation site deployed (if applicable)
- [ ] Search functionality works
- [ ] Documentation linked from README

---

## Notes

- Consider using VuePress, Docusaurus, or GitBook for documentation site
- Add Google Analytics to track documentation usage
- Implement feedback mechanism in documentation
- Keep documentation in sync with code changes
- Consider translation of documentation itself
