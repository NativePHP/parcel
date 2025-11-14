# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Parcel** is a standalone Laravel PHAR bundler specifically designed for NativePHP applications. Rather than maintaining a fork of Box, Parcel uses Box as a dependency and provides specialized tooling for Laravel/NativePHP deployment with enhanced path handling and PHAR optimization.

## Development Commands

### Essential Commands
- `composer install` - Install dependencies
- `composer qa` - Run full quality assurance (lint, static analysis, tests)
- `composer test` - Run Pest tests in parallel
- `composer pint` - Run Laravel Pint code formatter
- `composer phpstan` - Run PHPStan static analysis
- `composer static` - Run both Pint and PHPStan

### CLI Tool Commands
- `bin/parcel prepare [target-dir]` - Prepare Laravel project (combines push-stubs and patch-composer)
- `bin/parcel push-stubs [target-dir]` - Push NativePHP stubs to Laravel project
- `bin/parcel patch-composer [target-dir]` - Patch composer.json for PHAR compatibility
- `bin/parcel compile [working-dir]` - Compile Laravel project to PHAR using Box

### Global Installation Testing
- `composer global require nativephp/parcel` - Install globally for testing
- `parcel prepare` - Test global preparation
- `parcel compile` - Test compilation from any directory

## Architecture

### Core Components

**Symfony Console Application (`src/Application.php`):**
- Main entry point that registers all commands
- Simple extension of Symfony Console Application

**Commands (`src/Command/`):**
- `PrepareCommand` - Streamlined project setup combining push-stubs and patch-composer
- `PushStubsCommand` - Copies NativePHP stubs to Laravel projects
- `PatchComposerCommand` - Modifies composer.json for PHAR compilation
- `CompileCommand` - Wraps Box compilation with NativePHP enhancements

**RealPathMod Compactor (`src/Compactor/RealPathMod.php`):**
- Replaces `realpath()` calls with `nativephp_rp()` for PHAR-compatible path resolution
- Fixes Laravel's file path handling within PHAR environments
- Includes hotfix for Blade Icons service provider path resolution
- Modifies SplFileInfo to add proper `getRealPath()` method

### Key Stub Files

**Custom PHAR Stub (`stubs/custom-stub.php`):**
- Entry point for NativePHP PHAR bundles
- Handles NATIVEPHP_APP_DATA_PATH environment variable configuration
- Auto-creates external runtime directories (storage/, database/, bootstrap/cache/)
- Sets Laravel environment variables from single NATIVEPHP_APP_DATA_PATH
- Allows cli, cli-server, phpdbg, and embed SAPIs

**NativePHP Path Helper (`stubs/box-helper.php`):**
- Cross-platform path normalization with `nativephp_rp()` function
- Windows/Unix path compatibility
- PHAR-aware path resolution that works with Laravel's expectations

**PHAR Router (`stubs/phar-router.php`):**
- Web server routing for Laravel applications running from PHAR
- Static asset serving with proper MIME types
- Artisan CLI support

**Bundle Configuration (`stubs/box.json`):**
- Pre-configured Box settings for Laravel/NativePHP
- Optimized file exclusions and inclusions (excludes .sqlite files)
- Integrated RealPathMod compactor usage

**Custom Request Class (`stubs/App/Native/Request.php`):**
- Laravel Request class for CLI-to-HTTP conversion in NativePHP context

### Architecture Patterns
- **Composition over Inheritance**: Uses Box as dependency rather than forking
- **Command Pattern**: Symfony Console commands orchestrate Box operations
- **Compactor Pattern**: RealPathMod enhances Box's compilation process

## Testing Framework

- **Pest PHP** - Main testing framework
- Tests organized in `Feature/` and `Unit/` directories
- `tests/Pest.php` configures test base classes
- Run individual tests: `./vendor/bin/pest tests/Unit/NativePhpRpTest.php`

## Code Quality Tools

- **Laravel Pint** - Code formatting with Laravel preset
- **PHPStan Level 5** - Static analysis on `src/` directory
- Configuration files: `pint.json`, `phpstan.neon`

## Development Notes

- PHP 8.2+ required with ext-json and ext-phar
- Uses Box 4.0+ as primary dependency
- Symfony Console/Filesystem/Process components for CLI functionality
- Global CLI tool installed via Composer bin

### Path Resolution Architecture

Most Laravel PHAR issues stem from `realpath()` not working in PHAR context. The RealPathMod compactor automatically replaces these calls with `nativephp_rp()` which provides cross-platform path normalization and PHAR-aware resolution.

### Runtime Path Management with Phar::mount()

Parcel uses `Phar::mount()` to transparently redirect runtime directories from inside the PHAR to external filesystem locations, configured via a single environment variable:

**NATIVEPHP_APP_DATA_PATH Environment Variable:**
- Single variable that drives all runtime path mounting
- Automatically creates external directory structure: `$NATIVEPHP_APP_DATA_PATH/app/{storage,database,bootstrap/cache}`
- Uses `Phar::mount()` to redirect PHAR paths to external filesystem
- No environment variable pollution in the stub

**External Directory Structure:**
```
$NATIVEPHP_APP_DATA_PATH/
└── app/
    ├── storage/
    │   ├── logs/          (mounted to phar://storage/logs)
    │   └── framework/     (mounted to phar://storage/framework)
    ├── database/          (mounted to phar://database)
    │   └── database.sqlite
    └── bootstrap/
        └── cache/         (mounted to phar://bootstrap/cache)
```

**PHAR Exclusions (box.json):**
- `storage/logs` - excluded from PHAR, mounted at runtime
- `storage/framework` - excluded from PHAR, mounted at runtime
- `bootstrap/cache` - excluded from PHAR, mounted at runtime
- `database/*.sqlite` - excluded from PHAR
- `storage/app` - kept in PHAR for placeholder/gitignore files

**Mounting Implementation:**
The custom stub checks for `NATIVEPHP_APP_DATA_PATH`, creates the external directory structure, and uses `Phar::mount()` with relative paths:
```php
Phar::mount('storage/logs', $externalPath);
Phar::mount('storage/framework', $externalPath);
Phar::mount('bootstrap/cache', $externalPath);
Phar::mount('database', $externalPath);
```

Laravel accesses these paths normally (e.g., `storage()->path('logs/laravel.log')`) and they're transparently redirected to external filesystem by the PHAR mounting.