<?php

namespace Masum\AiTranslator\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masum\AiTranslator\Models\Language;

class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'country_code' => 'US', 'region' => 'North America'],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'country_code' => 'ES', 'region' => 'Europe'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'country_code' => 'FR', 'region' => 'Europe'],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'country_code' => 'DE', 'region' => 'Europe'],
            ['code' => 'bn', 'name' => 'Bengali', 'native_name' => 'বাংলা', 'country_code' => 'BD', 'region' => 'Asia'],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'country_code' => 'SA', 'region' => 'Middle East'],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी', 'country_code' => 'IN', 'region' => 'Asia'],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'country_code' => 'CN', 'region' => 'Asia'],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'country_code' => 'JP', 'region' => 'Asia'],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'country_code' => 'RU', 'region' => 'Europe'],
        ];

        $language = $this->faker->randomElement($languages);

        return [
            'code' => $language['code'] . '_' . $this->faker->unique()->randomNumber(4),
            'name' => $language['name'],
            'native_name' => $language['native_name'],
            'direction' => in_array($language['code'], ['ar']) ? 'rtl' : 'ltr',
            'is_active' => true,
            'is_default' => false,
            'country_code' => $language['country_code'],
            'region' => $language['region'],
        ];
    }

    /**
     * Indicate that the language is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the language is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the language is the default
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the language is RTL
     */
    public function rtl(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'rtl',
            'code' => 'ar',
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'country_code' => 'SA',
            'region' => 'Middle East',
        ]);
    }

    /**
     * Create English language
     */
    public function english(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
            'country_code' => 'US',
            'region' => 'North America',
        ]);
    }

    /**
     * Create Spanish language
     */
    public function spanish(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'es',
            'name' => 'Spanish',
            'native_name' => 'Español',
            'direction' => 'ltr',
            'country_code' => 'ES',
            'region' => 'Europe',
        ]);
    }

    /**
     * Create Bengali language
     */
    public function bengali(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'bn',
            'name' => 'Bengali',
            'native_name' => 'বাংলা',
            'direction' => 'ltr',
            'country_code' => 'BD',
            'region' => 'Asia',
        ]);
    }
}
