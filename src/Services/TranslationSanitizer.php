<?php

namespace Masum\AiTranslator\Services;

use Illuminate\Support\Str;

class TranslationSanitizer
{
    /**
     * Allowed HTML tags in translations
     */
    protected array $allowedTags = [
        'b', 'i', 'u', 'strong', 'em', 'a', 'br', 'p', 'span',
    ];

    /**
     * Allowed HTML attributes
     */
    protected array $allowedAttributes = [
        'a' => ['href', 'title', 'target'],
        'span' => ['class'],
    ];

    /**
     * Dangerous patterns to remove
     */
    protected array $dangerousPatterns = [
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/javascript:/i',
        '/on\w+\s*=/i', // onclick, onload, etc.
        '/<iframe\b[^>]*>(.*?)<\/iframe>/is',
        '/<object\b[^>]*>(.*?)<\/object>/is',
        '/<embed\b[^>]*>/is',
        '/data:text\/html/i',
    ];

    /**
     * Sanitize translation value
     */
    public function sanitize(string $value, array $options = []): string
    {
        $mode = $options['mode'] ?? config('ai-translator.sanitization.mode', 'moderate');

        return match ($mode) {
            'strict' => $this->sanitizeStrict($value),
            'moderate' => $this->sanitizeModerate($value),
            'permissive' => $this->sanitizePermissive($value),
            'none' => $value,
            default => $this->sanitizeModerate($value),
        };
    }

    /**
     * Strict sanitization - No HTML allowed
     */
    protected function sanitizeStrict(string $value): string
    {
        // Remove all HTML tags
        $value = strip_tags($value);

        // Decode HTML entities to prevent double encoding
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        // Encode special characters
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // Trim whitespace
        return trim($value);
    }

    /**
     * Moderate sanitization - Some HTML allowed with strict filtering
     */
    protected function sanitizeModerate(string $value): string
    {
        // Remove dangerous patterns first
        $value = $this->removeDangerousPatterns($value);

        // Allow specific HTML tags
        $allowedTagsString = '<' . implode('><', $this->allowedTags) . '>';
        $value = strip_tags($value, $allowedTagsString);

        // Sanitize attributes
        $value = $this->sanitizeAttributes($value);

        // Trim whitespace
        return trim($value);
    }

    /**
     * Permissive sanitization - Most HTML allowed but dangerous content removed
     */
    protected function sanitizePermissive(string $value): string
    {
        // Remove dangerous patterns
        $value = $this->removeDangerousPatterns($value);

        // Trim whitespace
        return trim($value);
    }

    /**
     * Remove dangerous patterns from value
     */
    protected function removeDangerousPatterns(string $value): string
    {
        foreach ($this->dangerousPatterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }

        return $value;
    }

    /**
     * Sanitize HTML attributes
     */
    protected function sanitizeAttributes(string $html): string
    {
        $dom = new \DOMDocument();

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);

        // Load HTML with UTF-8 encoding
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Process each element
        foreach ($this->allowedTags as $tag) {
            $elements = $xpath->query("//{$tag}");

            foreach ($elements as $element) {
                $allowedAttrs = $this->allowedAttributes[$tag] ?? [];

                // Get all attributes
                $attributes = [];
                foreach ($element->attributes as $attr) {
                    $attributes[] = $attr->name;
                }

                // Remove disallowed attributes
                foreach ($attributes as $attrName) {
                    if (!in_array($attrName, $allowedAttrs)) {
                        $element->removeAttribute($attrName);
                    }
                }

                // Sanitize href attributes
                if ($tag === 'a' && $element->hasAttribute('href')) {
                    $href = $element->getAttribute('href');

                    // Remove javascript: and data: protocols
                    if (preg_match('/^(javascript|data):/i', $href)) {
                        $element->removeAttribute('href');
                    }
                }
            }
        }

        // Get sanitized HTML
        $sanitized = $dom->saveHTML($dom->documentElement);

        // Remove added XML encoding tag
        $sanitized = str_replace('<?xml encoding="UTF-8">', '', $sanitized);

        return $sanitized;
    }

    /**
     * Sanitize translation key
     */
    public function sanitizeKey(string $key): string
    {
        // Remove any HTML
        $key = strip_tags($key);

        // Allow only alphanumeric, dots, underscores, and hyphens
        $key = preg_replace('/[^a-zA-Z0-9._-]/', '', $key);

        // Trim dots, underscores, hyphens from start and end
        $key = trim($key, '._-');

        return $key;
    }

    /**
     * Sanitize group name
     */
    public function sanitizeGroup(string $group): string
    {
        // Remove any HTML
        $group = strip_tags($group);

        // Allow only alphanumeric, underscores, and hyphens
        $group = preg_replace('/[^a-zA-Z0-9_-]/', '', $group);

        // Trim underscores and hyphens from start and end
        $group = trim($group, '_-');

        return $group;
    }

    /**
     * Validate if value contains potentially dangerous content
     */
    public function isDangerous(string $value): bool
    {
        foreach ($this->dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get sanitization errors/warnings
     */
    public function getSanitizationReport(string $original, string $sanitized): array
    {
        $report = [
            'is_modified' => $original !== $sanitized,
            'original_length' => strlen($original),
            'sanitized_length' => strlen($sanitized),
            'removed_content' => [],
        ];

        // Check for removed dangerous patterns
        foreach ($this->dangerousPatterns as $pattern) {
            if (preg_match($pattern, $original)) {
                $report['removed_content'][] = 'Removed dangerous pattern: ' . $pattern;
            }
        }

        // Check for removed HTML tags
        $originalTags = [];
        preg_match_all('/<(\w+)/', $original, $originalTags);
        $sanitizedTags = [];
        preg_match_all('/<(\w+)/', $sanitized, $sanitizedTags);

        $removedTags = array_diff($originalTags[1] ?? [], $sanitizedTags[1] ?? []);
        if (!empty($removedTags)) {
            $report['removed_content'][] = 'Removed HTML tags: ' . implode(', ', array_unique($removedTags));
        }

        return $report;
    }

    /**
     * Set custom allowed tags
     */
    public function setAllowedTags(array $tags): self
    {
        $this->allowedTags = $tags;
        return $this;
    }

    /**
     * Set custom allowed attributes
     */
    public function setAllowedAttributes(array $attributes): self
    {
        $this->allowedAttributes = $attributes;
        return $this;
    }

    /**
     * Add custom dangerous pattern
     */
    public function addDangerousPattern(string $pattern): self
    {
        $this->dangerousPatterns[] = $pattern;
        return $this;
    }
}
