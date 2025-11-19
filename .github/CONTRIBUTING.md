# Contributing to Laravel AI Translator

Thank you for considering contributing to Laravel AI Translator! This document provides guidelines for contributing to the project.

## Development Workflow

### Prerequisites

- PHP 8.2 or higher
- Composer
- Git

### Setup

1. **Fork and clone the repository**
   ```bash
   git clone https://github.com/your-username/ai-translation.git
   cd ai-translation
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Run tests**
   ```bash
   composer test
   ```

### Code Quality

Before submitting a pull request, ensure your code passes all quality checks:

```bash
# Run all quality checks
composer quality

# Individual checks
composer format          # Fix code style
composer format:test     # Check code style
composer analyse         # Run PHPStan
composer test            # Run tests
composer test:coverage   # Run tests with coverage
```

### Coding Standards

- Follow PSR-12 coding standards
- Use Laravel Pint for code formatting
- Add type hints for all parameters and return types
- Write PHPDoc comments for all public methods
- Maintain test coverage above 80%

### Pull Request Process

1. **Create a feature branch**
   ```bash
   git checkout -b feature/my-new-feature
   ```

2. **Make your changes**
   - Write tests for new features
   - Update documentation
   - Follow coding standards

3. **Run quality checks**
   ```bash
   composer quality
   ```

4. **Commit your changes**
   ```bash
   git add .
   git commit -m "Add feature: description"
   ```

5. **Push to your fork**
   ```bash
   git push origin feature/my-new-feature
   ```

6. **Create a Pull Request**
   - Provide a clear description
   - Reference any related issues
   - Ensure CI checks pass

### Continuous Integration

All pull requests must pass the following CI checks:

- **Tests**: All tests must pass on PHP 8.2 and 8.3
- **Code Coverage**: Minimum 80% coverage required
- **PHPStan**: Static analysis must pass (level 5)
- **Laravel Pint**: Code style must be correct
- **Security**: No known security vulnerabilities

CI runs automatically on all pull requests. You can see the status in the PR checks section.

### Testing

#### Writing Tests

- Place unit tests in `tests/Unit/`
- Place feature tests in `tests/Feature/`
- Use Pest PHP syntax
- Group tests with `->group()` for easy filtering

Example:
```php
test('can create a translation', function () {
    $translation = Translation::factory()->create();

    expect($translation)->toBeTranslation()
        ->and($translation->value)->not->toBeEmpty();
})->group('unit', 'translation');
```

#### Running Tests

```bash
# Run all tests
composer test

# Run specific group
vendor/bin/pest --group=unit

# Run with coverage
composer test:coverage

# Run specific test file
vendor/bin/pest tests/Unit/Models/TranslationTest.php
```

### Documentation

- Update README.md if you add new features
- Add inline comments for complex logic
- Update CHANGELOG.md following Keep a Changelog format
- Add examples for new features

### Reporting Issues

When reporting issues, please include:

- Laravel version
- PHP version
- Package version
- Steps to reproduce
- Expected vs actual behavior
- Error messages and stack traces

### Feature Requests

Feature requests are welcome! Please:

- Check if the feature already exists or is planned
- Provide clear use cases
- Explain why the feature would be useful
- Consider opening a discussion first for major features

### Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Focus on the code, not the person
- Help others learn and grow

### Questions?

If you have questions about contributing:

- Open a GitHub Discussion
- Check existing issues and pull requests
- Review the documentation

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to Laravel AI Translator! 🎉
