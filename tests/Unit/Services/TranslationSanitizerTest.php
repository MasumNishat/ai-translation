<?php

use Masum\AiTranslator\Services\TranslationSanitizer;

beforeEach(function () {
    $this->sanitizer = new TranslationSanitizer();
});

describe('TranslationSanitizer - Strict Mode', function () {
    test('removes all HTML tags in strict mode', function () {
        config(['ai-translator.sanitization.mode' => 'strict']);

        $input = '<script>alert("xss")</script>Hello <b>World</b>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'strict']);

        expect($result)->toBe('Hello World');
    })->group('sanitization', 'strict');

    test('encodes special characters in strict mode', function () {
        $input = '<>&"\'';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'strict']);

        expect($result)->toContain('&lt;')
            ->and($result)->toContain('&gt;');
    })->group('sanitization', 'strict');

    test('removes dangerous javascript in strict mode', function () {
        $input = '<a href="javascript:alert(\'xss\')">Click</a>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'strict']);

        expect($result)->not->toContain('javascript')
            ->and($result)->not->toContain('href');
    })->group('sanitization', 'strict');
});

describe('TranslationSanitizer - Moderate Mode', function () {
    test('allows safe HTML tags in moderate mode', function () {
        $input = 'Hello <b>World</b> and <i>Universe</i>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'moderate']);

        expect($result)->toContain('<b>World</b>')
            ->and($result)->toContain('<i>Universe</i>');
    })->group('sanitization', 'moderate');

    test('removes dangerous scripts in moderate mode', function () {
        $input = '<script>alert("xss")</script>Hello <b>World</b>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'moderate']);

        expect($result)->not->toContain('<script>')
            ->and($result)->toContain('<b>World</b>');
    })->group('sanitization', 'moderate');

    test('removes onclick handlers in moderate mode', function () {
        $input = '<a href="#" onclick="alert(\'xss\')">Link</a>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'moderate']);

        expect($result)->not->toContain('onclick');
    })->group('sanitization', 'moderate');

    test('allows safe links in moderate mode', function () {
        $input = '<a href="https://example.com">Link</a>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'moderate']);

        expect($result)->toContain('href="https://example.com"');
    })->group('sanitization', 'moderate');

    test('removes javascript protocol from links', function () {
        $input = '<a href="javascript:alert(\'xss\')">Bad Link</a>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'moderate']);

        expect($result)->not->toContain('javascript:');
    })->group('sanitization', 'moderate');

    test('removes disallowed HTML tags', function () {
        $input = '<script>bad</script><div>content</div><b>good</b>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'moderate']);

        expect($result)->not->toContain('<script>')
            ->and($result)->not->toContain('<div>')
            ->and($result)->toContain('<b>good</b>');
    })->group('sanitization', 'moderate');
});

describe('TranslationSanitizer - Permissive Mode', function () {
    test('allows most HTML in permissive mode', function () {
        $input = '<div class="container"><p>Hello <b>World</b></p></div>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'permissive']);

        expect($result)->toContain('<div')
            ->and($result)->toContain('<p>')
            ->and($result)->toContain('<b>');
    })->group('sanitization', 'permissive');

    test('still removes dangerous scripts in permissive mode', function () {
        $input = '<div><script>alert("xss")</script><p>Content</p></div>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'permissive']);

        expect($result)->not->toContain('<script>')
            ->and($result)->toContain('<p>Content</p>');
    })->group('sanitization', 'permissive');

    test('removes javascript protocol in permissive mode', function () {
        $input = '<a href="javascript:alert(\'xss\')">Link</a>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'permissive']);

        expect($result)->not->toContain('javascript:');
    })->group('sanitization', 'permissive');
});

describe('TranslationSanitizer - None Mode', function () {
    test('does not modify input when mode is none', function () {
        $input = '<script>alert("xss")</script>Hello';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'none']);

        expect($result)->toBe($input);
    })->group('sanitization', 'none');
});

describe('TranslationSanitizer - Key Sanitization', function () {
    test('sanitizes translation keys', function () {
        $input = 'auth.<script>login</script>.button';
        $result = $this->sanitizer->sanitizeKey($input);

        expect($result)->toBe('auth.login.button')
            ->and($result)->not->toContain('<script>');
    })->group('sanitization', 'key');

    test('allows only valid key characters', function () {
        $input = 'auth.login!@#$%button';
        $result = $this->sanitizer->sanitizeKey($input);

        expect($result)->toBe('auth.loginbutton');
    })->group('sanitization', 'key');

    test('trims invalid characters from key edges', function () {
        $input = '...auth.login...';
        $result = $this->sanitizer->sanitizeKey($input);

        expect($result)->toBe('auth.login');
    })->group('sanitization', 'key');
});

describe('TranslationSanitizer - Group Sanitization', function () {
    test('sanitizes group names', function () {
        $input = 'auth<script>';
        $result = $this->sanitizer->sanitizeGroup($input);

        expect($result)->toBe('auth')
            ->and($result)->not->toContain('<script>');
    })->group('sanitization', 'group');

    test('allows only alphanumeric and underscores in groups', function () {
        $input = 'auth-group_123!@#';
        $result = $this->sanitizer->sanitizeGroup($input);

        expect($result)->toBe('auth-group_123');
    })->group('sanitization', 'group');
});

describe('TranslationSanitizer - Dangerous Content Detection', function () {
    test('detects script tags as dangerous', function () {
        $input = '<script>alert("xss")</script>';

        expect($this->sanitizer->isDangerous($input))->toBeTrue();
    })->group('sanitization', 'detection');

    test('detects javascript protocol as dangerous', function () {
        $input = '<a href="javascript:alert()">Link</a>';

        expect($this->sanitizer->isDangerous($input))->toBeTrue();
    })->group('sanitization', 'detection');

    test('detects onclick handlers as dangerous', function () {
        $input = '<div onclick="alert()">Click</div>';

        expect($this->sanitizer->isDangerous($input))->toBeTrue();
    })->group('sanitization', 'detection');

    test('safe content is not dangerous', function () {
        $input = '<b>Hello</b> <a href="https://example.com">Link</a>';

        expect($this->sanitizer->isDangerous($input))->toBeFalse();
    })->group('sanitization', 'detection');

    test('detects iframes as dangerous', function () {
        $input = '<iframe src="https://evil.com"></iframe>';

        expect($this->sanitizer->isDangerous($input))->toBeTrue();
    })->group('sanitization', 'detection');
});

describe('TranslationSanitizer - Sanitization Report', function () {
    test('generates report when content is modified', function () {
        $original = '<script>alert("xss")</script>Hello';
        $sanitized = $this->sanitizer->sanitize($original, ['mode' => 'moderate']);

        $report = $this->sanitizer->getSanitizationReport($original, $sanitized);

        expect($report['is_modified'])->toBeTrue()
            ->and($report['original_length'])->toBeGreaterThan($report['sanitized_length']);
    })->group('sanitization', 'report');

    test('report shows removed dangerous patterns', function () {
        $original = '<script>bad</script>Good';
        $sanitized = $this->sanitizer->sanitize($original, ['mode' => 'moderate']);

        $report = $this->sanitizer->getSanitizationReport($original, $sanitized);

        expect($report['removed_content'])->not->toBeEmpty();
    })->group('sanitization', 'report');

    test('report indicates no modification for safe content', function () {
        $original = 'Hello World';
        $sanitized = $this->sanitizer->sanitize($original, ['mode' => 'strict']);

        $report = $this->sanitizer->getSanitizationReport($original, $sanitized);

        expect($report['is_modified'])->toBeFalse();
    })->group('sanitization', 'report');
});

describe('TranslationSanitizer - Custom Configuration', function () {
    test('can set custom allowed tags', function () {
        $this->sanitizer->setAllowedTags(['b', 'i']);

        $input = '<b>bold</b> <strong>strong</strong>';
        $result = $this->sanitizer->sanitize($input, ['mode' => 'moderate']);

        expect($result)->toContain('<b>bold</b>')
            ->and($result)->not->toContain('<strong>');
    })->group('sanitization', 'custom');

    test('can add custom dangerous patterns', function () {
        $this->sanitizer->addDangerousPattern('/<custom>/i');

        $input = '<custom>test</custom>';

        expect($this->sanitizer->isDangerous($input))->toBeTrue();
    })->group('sanitization', 'custom');
});

describe('TranslationSanitizer - XSS Prevention', function () {
    test('prevents common XSS attack vectors', function () {
        $attacks = [
            '<script>alert(document.cookie)</script>',
            '<img src=x onerror=alert(1)>',
            '<svg onload=alert(1)>',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<a href="javascript:alert(1)">Click</a>',
            '<div onclick="alert(1)">Click</div>',
            '<input onfocus=alert(1) autofocus>',
        ];

        foreach ($attacks as $attack) {
            $result = $this->sanitizer->sanitize($attack, ['mode' => 'moderate']);

            expect($this->sanitizer->isDangerous($attack))->toBeTrue();
            expect($result)->not->toContain('alert');
        }
    })->group('sanitization', 'xss');

    test('allows safe HTML formatting', function () {
        $safe = [
            '<b>bold text</b>',
            '<i>italic text</i>',
            '<a href="https://example.com">link</a>',
            '<br>',
            '<p>paragraph</p>',
        ];

        foreach ($safe as $html) {
            $result = $this->sanitizer->sanitize($html, ['mode' => 'moderate']);

            expect($this->sanitizer->isDangerous($html))->toBeFalse();
        }
    })->group('sanitization', 'xss');
});
