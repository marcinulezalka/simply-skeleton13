<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Development Setup
- Local packages are located in `packages/simplysmart/<package-name>`.
- Always edit package code directly inside `packages/simplysmart/<package-name>/` (do not edit inside `vendor/`).
- Package dependencies are symlinked via Composer path repository (`packages/*/*`).
- Each package in `packages/` is a separate Git repository, ignored by the skeleton13 repository.
- Coding standards (main project and every package): PHP ^8.4, PSR-4 autoloading (one class per file, namespace mirrors the directory), PER Coding Style via Laravel Pint `per` preset (`pint.json`). Run `composer lint` before finishing; `composer format` fixes style.
- Versioning: one shared implementation in the dev package `simplysmart/tools` (`packages/simplysmart/tools`) releases the main project and every package. Never copy it into a package; run `vendor/bin/version-release` for the main project, `vendor/bin/version-release --root=packages/<vendor>/<package>` for a package. It really releases — never run it just to test; tests use temp dirs.
