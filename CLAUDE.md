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
- Optimized file exclusions and inclusions
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