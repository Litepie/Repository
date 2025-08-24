# Contributing to Litepie Repository

Thank you for considering contributing to the Litepie Repository package! This document provides guidelines and information for contributors.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Setup](#development-setup)
4. [Making Contributions](#making-contributions)
5. [Coding Standards](#coding-standards)
6. [Testing](#testing)
7. [Pull Request Process](#pull-request-process)
8. [Release Process](#release-process)

## Code of Conduct

This project adheres to a code of conduct that we expect all contributors to follow:

- **Be respectful**: Treat everyone with respect and kindness
- **Be inclusive**: Welcome newcomers and help them get started
- **Be constructive**: Provide helpful feedback and suggestions
- **Be collaborative**: Work together towards common goals

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Laravel 11.x or 12.x knowledge
- Git

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/repository.git
   cd repository
   ```

3. Add the upstream repository:
   ```bash
   git remote add upstream https://github.com/litepie/repository.git
   ```

## Development Setup

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
composer test
```

### Code Style Check

```bash
composer style-check
```

### Fix Code Style

```bash
composer style-fix
```

### Static Analysis

```bash
composer analyze
```

## Making Contributions

### Types of Contributions

We welcome several types of contributions:

- **Bug fixes**: Fix issues in the codebase
- **Feature additions**: Add new functionality
- **Documentation**: Improve or add documentation
- **Tests**: Add or improve test coverage
- **Performance**: Optimize existing code

### Before You Start

1. Check existing issues and pull requests to avoid duplicates
2. For new features, open an issue first to discuss the proposal
3. For bug fixes, provide a clear description of the issue

### Branch Naming

Use descriptive branch names:

- `feature/add-soft-delete-support`
- `fix/pagination-bug`
- `docs/update-readme`
- `refactor/optimize-queries`

## Coding Standards

### PSR Standards

This project follows PSR standards:

- **PSR-1**: Basic Coding Standard
- **PSR-4**: Autoloading Standard
- **PSR-12**: Extended Coding Style

### PHP CS Fixer

We use PHP CS Fixer with a custom configuration. Run the fixer before committing:

```bash
composer style-fix
```

### Code Style Guidelines

#### Class Structure

```php
<?php

declare(strict_types=1);

namespace Litepie\Repository;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Class description
 */
class ExampleClass
{
    // Constants first
    public const EXAMPLE_CONSTANT = 'value';

    // Properties
    protected string $property;

    // Constructor
    public function __construct(string $property)
    {
        $this->property = $property;
    }

    // Public methods
    public function publicMethod(): string
    {
        return $this->property;
    }

    // Protected methods
    protected function protectedMethod(): void
    {
        // Implementation
    }

    // Private methods
    private function privateMethod(): array
    {
        return [];
    }
}
```

#### Method Documentation

All public methods should have proper DocBlocks:

```php
/**
 * Find a record by ID with optional relationships.
 *
 * @param  int|string  $id  The record ID
 * @param  array  $with  Relationships to eager load
 * @return Model|null  The found model or null
 * 
 * @throws ModelNotFoundException When record not found and $fail is true
 */
public function find($id, array $with = [], bool $fail = false): ?Model
{
    // Implementation
}
```

#### Array Formatting

```php
// Short arrays
$array = [
    'key1' => 'value1',
    'key2' => 'value2',
];

// Method calls
$result = $this->repository->findWhere([
    ['status', '=', 'active'],
    ['created_at', '>', now()->subDays(30)],
]);
```

### Naming Conventions

- **Classes**: PascalCase (`BaseRepository`)
- **Methods**: camelCase (`findById`)
- **Properties**: camelCase (`$queryBuilder`)
- **Constants**: UPPER_SNAKE_CASE (`DEFAULT_LIMIT`)
- **Variables**: camelCase (`$userData`)

## Testing

### Test Structure

Tests are organized in the `tests` directory:

```
tests/
├── Feature/           # Integration tests
├── Unit/             # Unit tests
├── TestCase.php      # Base test case
└── Helpers/          # Test helpers
```

### Writing Tests

#### Unit Tests

```php
<?php

namespace Litepie\Repository\Tests\Unit;

use Litepie\Repository\Tests\TestCase;
use Litepie\Repository\BaseRepository;

class BaseRepositoryTest extends TestCase
{
    protected BaseRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BaseRepository();
    }

    /** @test */
    public function it_can_create_a_record(): void
    {
        $data = ['name' => 'Test User'];
        
        $result = $this->repository->create($data);
        
        $this->assertInstanceOf(Model::class, $result);
        $this->assertEquals('Test User', $result->name);
    }
}
```

#### Feature Tests

```php
<?php

namespace Litepie\Repository\Tests\Feature;

use Litepie\Repository\Tests\TestCase;

class RepositoryIntegrationTest extends TestCase
{
    /** @test */
    public function it_can_filter_and_paginate_results(): void
    {
        // Create test data
        $this->createUsers(50);
        
        // Test filtering and pagination
        $result = $this->userRepository
            ->filter(['status' => 'active'])
            ->paginate(10);
            
        $this->assertCount(10, $result->items());
        $this->assertEquals(25, $result->total()); // Assuming 25 active users
    }
}
```

### Test Coverage

Aim for high test coverage:

- **Unit tests**: Test individual methods in isolation
- **Feature tests**: Test complete workflows
- **Edge cases**: Test boundary conditions and error scenarios

Run coverage report:

```bash
composer test-coverage
```

### Testing Guidelines

1. **Test names**: Use descriptive names that explain what is being tested
2. **Arrange-Act-Assert**: Structure tests clearly
3. **One assertion per test**: Focus on single behavior
4. **Mock external dependencies**: Use mocks and stubs appropriately
5. **Test edge cases**: Include boundary conditions and error scenarios

## Pull Request Process

### Before Submitting

1. **Update your branch**:
   ```bash
   git checkout main
   git pull upstream main
   git checkout your-branch
   git rebase main
   ```

2. **Run all checks**:
   ```bash
   composer check-all
   ```

3. **Update documentation** if needed

4. **Add tests** for new functionality

### Pull Request Requirements

- **Clear title**: Summarize the changes
- **Detailed description**: Explain what and why
- **Link issues**: Reference related issues
- **Screenshots**: Include if UI changes
- **Breaking changes**: Clearly mark and explain

### Pull Request Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Changes Made
- List specific changes
- Include any breaking changes

## Testing
- [ ] Tests pass
- [ ] New tests added
- [ ] Manual testing completed

## Documentation
- [ ] README updated
- [ ] Documentation updated
- [ ] Examples added

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Tests added/updated
- [ ] Documentation updated
```

### Review Process

1. **Automated checks**: Must pass CI/CD pipeline
2. **Code review**: At least one maintainer review
3. **Testing**: All tests must pass
4. **Documentation**: Must be updated for new features

## Release Process

### Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Checklist

1. **Update version** in relevant files
2. **Update CHANGELOG.md**
3. **Tag the release**
4. **Create GitHub release**
5. **Update documentation**

### Changelog Format

```markdown
## [1.2.0] - 2024-01-15

### Added
- New filtering capabilities
- Advanced search functionality

### Changed
- Improved performance of join operations

### Fixed
- Fixed pagination bug with filters

### Deprecated
- Old filter method (use `filter()` instead)

### Removed
- Removed deprecated methods

### Security
- Fixed SQL injection vulnerability
```

## Development Workflow

### Daily Development

1. **Start from main**:
   ```bash
   git checkout main
   git pull upstream main
   ```

2. **Create feature branch**:
   ```bash
   git checkout -b feature/your-feature
   ```

3. **Make changes and commit**:
   ```bash
   git add .
   git commit -m "feat: add new feature"
   ```

4. **Push and create PR**:
   ```bash
   git push origin feature/your-feature
   ```

### Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): description

[optional body]

[optional footer]
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Tests
- `chore`: Maintenance

Examples:
```
feat(repository): add soft delete support
fix(filter): resolve pagination issue with filters
docs(readme): update installation instructions
test(repository): add tests for join operations
```

## Getting Help

### Resources

- **Documentation**: Check the docs directory
- **Examples**: See EXAMPLES.md
- **Issues**: Search existing GitHub issues
- **Discussions**: Use GitHub Discussions for questions

### Contact

- **GitHub Issues**: For bugs and feature requests
- **GitHub Discussions**: For questions and general discussion
- **Email**: maintainers@litepie.com

## Recognition

Contributors will be recognized in:

- **CONTRIBUTORS.md**: List of all contributors
- **GitHub releases**: Notable contributions mentioned
- **Documentation**: Credit for significant improvements

Thank you for contributing to Litepie Repository! 🎉
