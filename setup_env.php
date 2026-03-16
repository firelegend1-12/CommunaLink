<?php
/**
 * Environment Setup Script
 * Run this once to create your .env file from .env.example
 * 
 * Usage: php setup_env.php
 */

$env_example = __DIR__ . '/.env.example';
$env_file = __DIR__ . '/.env';

if (file_exists($env_file)) {
    echo "⚠️  .env file already exists!\n";
    echo "If you want to recreate it, delete the existing .env file first.\n";
    exit(1);
}

if (!file_exists($env_example)) {
    echo "❌ .env.example file not found!\n";
    exit(1);
}

// Copy .env.example to .env
if (copy($env_example, $env_file)) {
    echo "✅ Created .env file from .env.example\n";
    echo "📝 Please edit .env file and add your actual credentials:\n";
    echo "   - Database credentials\n";
    echo "   - Email service credentials (Mailgun or Gmail)\n";
    echo "\n";
    echo "⚠️  IMPORTANT: Never commit .env to version control!\n";
    echo "   The .env file is already in .gitignore\n";
} else {
    echo "❌ Failed to create .env file. Please check file permissions.\n";
    exit(1);
}

