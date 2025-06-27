# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Parcel** is a standalone Laravel PHAR bundler specifically designed for NativePHP applications. Rather than maintaining a fork of Box, Parcel uses Box as a dependency and provides specialized tooling for Laravel/NativePHP deployment with enhanced path handling and PHAR optimization.

### Key Enhancements Over Base Box

**RealPathMod Compactor (`src/Compactor/RealPathMod.php`):**
- Replaces `realpath()` calls with `nativephp_rp()` for PHAR-compatible path resolution
- Fixes Laravel's file path handling within PHAR environments
- Includes hotfix for Blade Icons service provider path resolution
- Modifies SplFileInfo to add proper `getRealPath()` method

**NativePHP Path Helper (`res/stubs/box-helper.php`):**
- Cross-platform path normalization with Windows/Unix support
- Intelligent absolute vs relative path handling within PHAR context
- Directory trailing slash normalization for consistency
- PHAR-aware path resolution that works with Laravel's expectations

**PHAR Router (`res/stubs/phar-router.php`):**
- Web server routing for Laravel applications running from PHAR
- Static asset serving (CSS, JS, images) with proper MIME types
- Artisan command-line interface support
- Entry point routing to Laravel's public/index.php

**NativePHP Bundle Configuration (`res/stubs/box.json`):**
- Pre-configured for Laravel application structure
- Optimized file exclusions for smaller bundle size
- NativePHP-specific directory and file inclusions
- Integrated RealPathMod compactor usage

## Development Commands

### Essential Commands
- `composer install` - Install dependencies
- `bin/parcel push-stubs [target-dir]` - Push NativePHP stubs to Laravel project
- `bin/parcel compile [working-dir]` - Compile Laravel project to PHAR using Box

### Global Installation Testing
- `composer global require parcel/parcel` - Install globally for testing
- `parcel push-stubs /path/to/laravel-project` - Test global installation
- `parcel compile` - Test compilation from any directory

### Development Workflow
- `composer install` - Install dependencies
- `./bin/parcel push-stubs tests/fixtures/laravel-app` - Test stub pushing
- `./bin/parcel compile tests/fixtures/laravel-app` - Test compilation

## Architecture

### Core Components

**Main Classes:**
- `Parcel\Application` - Symfony Console application with Parcel commands
- `Parcel\Command\PushStubsCommand` - Copies NativePHP stubs to Laravel projects
- `Parcel\Command\CompileCommand` - Wraps Box compilation with NativePHP enhancements
- `Parcel\Compactor\RealPathMod` - Custom compactor for Laravel PHAR path compatibility

**Architecture Pattern:**
- **Composition over Inheritance**: Uses Box as dependency rather than forking
- **Command Pattern**: Symfony Console commands that orchestrate Box operations
- **Decorator Pattern**: RealPathMod compactor enhances Box's compilation process

### Workflow
1. **Push Stubs**: Copy NativePHP stubs and create optimized box.json for Laravel project
2. **Compile**: Execute Box compilation with RealPathMod compactor and Laravel-specific settings
3. **Box Integration**: Use Process component to call `vendor/bin/box` with enhanced configuration

### Project Structure
```
parcel/
├── bin/parcel              # CLI entry point
├── src/
│   ├── Application.php     # Symfony Console app
│   ├── Command/           # Push stubs & compile commands
│   └── Compactor/         # RealPathMod compactor
├── stubs/                 # NativePHP stub files
└── composer.json          # Global CLI tool configuration
```

### Key Files
- `bin/parcel` - CLI entry point for global installation
- `src/Compactor/RealPathMod.php` - **Laravel-specific path compactor**
- `stubs/box-helper.php` - **NativePHP path resolution helper**
- `stubs/phar-router.php` - **Laravel PHAR web routing**
- `stubs/box.json` - **NativePHP bundle configuration template**
- `stubs/App/Native/Request.php` - **Custom Laravel Request class for CLI-to-HTTP conversion**

## Development Notes

- PHP 8.2+ required 
- Uses Box as dependency instead of fork
- Symfony Console for CLI commands
- Process component for Box integration
- Minimal codebase focused on NativePHP-specific functionality

## Parcel-Specific Development

When working on Parcel enhancements:

**Path Resolution Issues:** Most Laravel PHAR issues stem from `realpath()` not working in PHAR context. The RealPathMod compactor automatically replaces these calls with `nativephp_rp()`.

**Testing Laravel Integration:** Use the NativePHP bundle configuration (`res/stubs/box.json`) as a starting point for Laravel application packaging.

**Cross-Platform Compatibility:** Path handling code must work on both Windows and Unix systems - the `nativephp_rp()` function handles this normalization.

**Recent Fixes Applied:**
- Path normalization improvements for Windows compatibility
- Blade Icons service provider path resolution
- Directory trailing slash handling
- String vs object handling in path operations