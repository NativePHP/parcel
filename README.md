# Parcel

A Laravel PHAR bundler designed specifically for NativePHP applications. Parcel provides specialized tools for packaging Laravel applications into PHAR files with proper path handling and NativePHP-specific optimizations.

## Installation

Install Parcel globally via Composer:

```bash
composer global require parcel/parcel
```

Make sure your global Composer bin directory is in your PATH.

## Usage

### 1. Push Stubs to Laravel Project

Navigate to your Laravel project and run:

```bash
parcel push-stubs
```

This will:
- Copy NativePHP-specific stub files to your project
- Create a `box.json` configuration optimized for Laravel
- Set up the RealPathMod compactor for PHAR compatibility

### 2. Compile PHAR

```bash
parcel compile
```

This will:
- Use Box to compile your Laravel project into a PHAR
- Apply NativePHP path fixes automatically
- Create an optimized bundle in the `build/` directory

## Features

- **RealPathMod Compactor**: Automatically fixes `realpath()` calls for PHAR compatibility
- **NativePHP Path Helper**: Cross-platform path normalization with `nativephp_rp()` function
- **PHAR Router**: Web server routing for Laravel applications running from PHAR
- **Optimized Configuration**: Pre-configured Box settings for Laravel applications

## Requirements

- PHP 8.2+
- Composer
- [humbug/box](https://github.com/box-project/box) (automatically handled)

## Commands

### `parcel push-stubs [target-dir]`

Pushes NativePHP stubs and configuration to a Laravel project.

**Arguments:**
- `target-dir` - Target Laravel project directory (default: current directory)

**Options:**
- `--force, -f` - Overwrite existing files

### `parcel compile [working-dir]`

Compiles a Laravel project into a PHAR using Box.

**Arguments:**
- `working-dir` - Working directory for compilation (default: current directory)

**Options:**
- `--config, -c` - Path to box.json configuration file
- `--debug` - Enable debug output
- `--no-parallel` - Disable parallel processing
- `--no-restart` - Do not restart the PHP process

## License

MIT License
