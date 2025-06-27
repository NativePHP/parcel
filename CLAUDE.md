# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **Parcel**, a fork of [box-project/box](https://github.com/box-project/box) that adds conveniences for boxing up Laravel applications into `.phar` files, primarily for use with NativePHP. It's a PHP 8.2+ application that creates PHAR (PHP Archive) files with advanced features like PHP-Scoper integration, compaction, and requirement checking.

## Development Commands

### Essential Commands
- `make help` - Show all available Makefile targets
- `composer install` - Install dependencies
- `make vendor_install` - Install dependencies and touch files
- `make clean` - Clean all build artifacts

### Building & Compilation
- `make compile` - Compile the application into a PHAR (creates `bin/box.phar`)
- `make $(SCOPED_BOX_BIN)` - Build the scoped PHAR with PHP-Scoper isolation
- `bin/box compile` - Use Box to compile PHARs from configuration
- `bin/box validate` - Validate box.json configuration files

### Testing
- `make test` - Run all tests (unit, integration, e2e)
- `make phpunit` - Run PHPUnit tests only
- `make phpunit_phar_readonly` - Run tests with phar.readonly=0
- `make phpunit_phar_writeable` - Run tests with phar.readonly=1
- `make test_e2e` - Run end-to-end tests
- `vendor/bin/phpunit --testsuite=Tests` - Run specific test suite
- `vendor/bin/phpunit --testsuite=AutoReviewTests` - Run auto-review tests

### Code Quality
- `make cs` - Fix code style (runs PHP-CS-Fixer, Rector, and Composer normalize)
- `make cs_lint` - Check code style without fixing
- `make autoreview` - Run all auto-review checks
- `make infection` - Run Mutation testing with Infection
- `make phpbench` - Run performance benchmarks

### Requirement Checker
- `make requirement_checker_check` - Run requirement checker tests
- `make dump_requirement_checker` - Generate requirement checker artifacts
- `cd requirement-checker && make check` - Run requirement checker validation

## Architecture

### Core Components

**Main Classes:**
- `KevinGH\Box\Box` - Core PHAR building engine with file processing, compaction, and signing
- `KevinGH\Box\Configuration\Configuration` - Configuration management for box.json files
- `KevinGH\Box\Console\Application` - Console application entry point with all commands

**Key Namespaces:**
- `KevinGH\Box\Compactor\` - File compaction (PHP, JSON, PhpScoper, Placeholder replacement)
- `KevinGH\Box\Console\Command\` - CLI commands (compile, diff, extract, info, verify, etc.)
- `KevinGH\Box\Phar\` - PHAR manipulation (compression, signing, diffing, metadata)
- `KevinGH\Box\PhpScoper\` - PHP-Scoper integration for namespace isolation
- `KevinGH\Box\RequirementChecker\` - Runtime requirement validation
- `KevinGH\Box\Composer\` - Composer integration and autoload handling

### Configuration System
- Primary config: `box.json` or `box.json.dist` (JSON Schema: `res/schema.json`)
- PHP-Scoper config: `scoper.inc.php` for namespace isolation
- Supports compactors, file filtering, signing algorithms, compression, metadata

### Build Process
1. Load and validate configuration
2. Process files through compactors (PHP minification, JSON compaction, scoping)
3. Handle Composer artifacts and autoloading
4. Apply requirement checker if configured  
5. Create PHAR with compression and signing
6. Validate final PHAR integrity

### Testing Strategy
- **Unit Tests:** `tests/` - Core functionality testing
- **Integration Tests:** Composer integration, PHAR operations
- **E2E Tests:** Full compilation workflows via Makefile.e2e
- **Benchmarks:** Performance testing in `tests/Benchmark/`
- **Auto-Review:** Code quality and documentation validation

### Key Files
- `bin/box` - Main CLI entry point
- `src/functions.php` - Global utility functions
- `res/requirement-checker/` - Runtime requirement validation components
- `fixtures/` - Test fixtures and examples
- `requirement-checker/` - Standalone requirement checker package

## Development Notes

- PHP 8.2+ required with extensions: iconv, mbstring, phar
- Uses Composer 2.2+ plugin API
- Supports parallel processing via amphp/parallel
- Heavy use of Symfony components (Finder, Process, Filesystem)
- PHP-CS-Fixer for code style, Rector for modernization
- Infection for mutation testing coverage