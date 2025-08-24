#!/bin/bash

# Litepie Repository Package Setup Script
# This script helps you get started with the package development

echo "🚀 Setting up Litepie Repository Package..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    exit 1
fi

# Install dependencies
echo "📦 Installing dependencies..."
composer install --prefer-dist --no-interaction

# Create necessary directories if they don't exist
echo "📁 Creating directories..."
mkdir -p tests/Unit
mkdir -p tests/Feature
mkdir -p stubs
mkdir -p config

# Set up git hooks (optional)
if [ -d ".git" ]; then
    echo "🔧 Setting up git hooks..."
    # You can add pre-commit hooks here
fi

# Make sure the package is properly configured
echo "⚙️  Checking configuration..."

# Run tests to ensure everything is working
echo "🧪 Running tests..."
if composer test; then
    echo "✅ All tests passed!"
else
    echo "❌ Some tests failed. Please check the output above."
fi

echo ""
echo "🎉 Setup complete!"
echo ""
echo "Next steps:"
echo "1. Update composer.json with your package details"
echo "2. Customize the configuration in config/repository.php"
echo "3. Write your repository implementations"
echo "4. Add tests for your custom functionality"
echo "5. Update README.md with your specific documentation"
echo ""
echo "Available commands:"
echo "- composer test          # Run tests"
echo "- composer test-coverage # Run tests with coverage"
echo "- composer format        # Format code"
echo "- composer analyze       # Run static analysis"
echo ""
echo "Happy coding! 🎯"
