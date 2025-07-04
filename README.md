# Parcel

A Laravel PHAR bundler designed specifically for NativePHP applications. Parcel provides specialized tools for packaging Laravel applications into PHAR files with proper path handling and NativePHP-specific optimizations.

## Installation

Install Parcel globally via Composer:

```bash
composer global require nativephp/parcel
```

Make sure your global Composer bin directory is in your PATH.

### CI/CD Environment (AWS CodeBuild)

For CI environments like AWS CodeBuild, you'll need to authenticate with the private repository. Set up authentication by configuring Composer with your credentials in the buildspec.yml or environment variables. For GitHub private repositories, use a personal access token:

```bash
composer config -g github-oauth.github.com $GITHUB_TOKEN
composer global require nativephp/parcel
```

Ensure your `GITHUB_TOKEN` environment variable is configured in your CodeBuild project settings with appropriate repository access permissions.

## Usage

### Quick Start

Navigate to your Laravel project and run:

```bash
parcel prepare
parcel compile
```

### Individual Commands

#### 1. Prepare Project (Recommended)

```bash
parcel prepare
```

This combines both preparation steps:
- Pushes NativePHP-specific stub files to your project
- Patches composer.json for PHAR compatibility
- Creates optimized Box configuration

#### 2. Manual Steps (Optional)

If you prefer to run steps individually:

```bash
# Push stub files and configuration
parcel push-stubs

# Patch composer.json
parcel patch-composer
```

#### 3. Compile PHAR

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

### `parcel prepare [target-dir]`

Prepares a Laravel project for PHAR compilation by running both push-stubs and patch-composer.

**Arguments:**
- `target-dir` - Target Laravel project directory (default: current directory)

**Options:**
- `--force, -f` - Overwrite existing files

### `parcel push-stubs [target-dir]`

Pushes NativePHP stubs and configuration to a Laravel project.

**Arguments:**
- `target-dir` - Target Laravel project directory (default: current directory)

**Options:**
- `--force, -f` - Overwrite existing files

### `parcel patch-composer [target-dir]`

Patches composer.json for NativePHP PHAR compilation.

**Arguments:**
- `target-dir` - Target Laravel project directory (default: current directory)

### `parcel compile [working-dir]`

Compiles a Laravel project into a PHAR using Box.

**Arguments:**
- `working-dir` - Working directory for compilation (default: current directory)

**Options:**
- `--config, -c` - Path to box.json configuration file
- `--debug` - Enable debug output

## License

MIT License
