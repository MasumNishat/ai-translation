<?php

namespace Masum\AiTranslator\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;

class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        $groups = ['common', 'auth', 'validation', 'pages', 'messages', 'errors', 'navigation'];
        $keys = [
            'common' => ['save', 'cancel', 'delete', 'edit', 'create', 'update', 'submit', 'back', 'next', 'previous'],
            'auth' => ['login', 'logout', 'register', 'forgot_password', 'reset_password', 'remember_me'],
            'validation' => ['required', 'email', 'min', 'max', 'confirmed', 'unique'],
            'pages' => ['home', 'about', 'contact', 'services', 'privacy', 'terms'],
            'messages' => ['success', 'error', 'warning', 'info', 'welcome'],
            'errors' => ['404', '500', 'unauthorized', 'forbidden', 'not_found'],
            'navigation' => ['menu', 'footer', 'header', 'sidebar'],
        ];

        $group = $this->faker->randomElement($groups);
        $keyOptions = $keys[$group] ?? ['sample'];
        $keyName = $this->faker->randomElement($keyOptions);

        return [
            'key' => $group . '.' . $keyName . '_' . $this->faker->unique()->randomNumber(4),
            'value' => $this->faker->sentence(),
            'language_id' => Language::factory(),
            'group' => $group,
        ];
    }

    /**
     * Set specific translation key
     */
    public function withKey(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
        ]);
    }

    /**
     * Set specific translation value
     */
    public function withValue(string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value,
        ]);
    }

    /**
     * Set specific group
     */
    public function withGroup(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => $group,
        ]);
    }

    /**
     * Set specific language
     */
    public function forLanguage(Language $language): static
    {
        return $this->state(fn (array $attributes) => [
            'language_id' => $language->id,
        ]);
    }

    /**
     * Create translation without value (missing translation)
     */
    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => null,
        ]);
    }

    /**
     * Create translation for auth group
     */
    public function auth(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'auth',
            'key' => 'auth.' . $this->faker->randomElement(['login', 'logout', 'register']),
        ]);
    }

    /**
     * Create translation for validation group
     */
    public function validation(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'validation',
            'key' => 'validation.' . $this->faker->randomElement(['required', 'email', 'min', 'max']),
        ]);
    }

    /**
     * Create common translation
     */
    public function common(): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => 'common',
            'key' => 'common.' . $this->faker->randomElement(['save', 'cancel', 'delete', 'edit']),
        ]);
    }
}
