<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

## Development Setup
- Local packages are located in `packages/simplysmart/<package-name>`.
- Always edit package code directly inside `packages/simplysmart/<package-name>/` (do not edit inside `vendor/`).
- Package dependencies are symlinked via Composer path repository (`packages/*/*`).
- Each package in `packages/` is a separate Git repository, ignored by the skeleton13 repository.
- Coding standards (main project and every package): PHP ^8.4, `declare(strict_types=1);` in every PHP file, PSR-4 autoloading (one class per file, namespace mirrors the directory), PER Coding Style via Laravel Pint `per` preset (`pint.json`). See Quality Gates below.
- Versioning: one shared implementation in the dev package `simplysmart/tools` (`packages/simplysmart/tools`) releases the main project and every package. Never copy it into a package; run `vendor/bin/version-release` for the main project, `vendor/bin/version-release --root=packages/<vendor>/<package>` for a package. It really releases — to preview, add `--dry-run` (no files change); tests use temp dirs.

## Framework Footprint
Goal: upgrading Laravel stays a `composer.json` bump plus `composer update`, with no merge work in the skeleton.
- **Stock framework files**: The skeleton is stock Laravel plus `.env` and runtime infrastructure. Framework files (`config/*.php`, `bootstrap/`, `routes/`, `app/Providers/`) stay as Laravel ships them; the only allowed change is `declare(strict_types=1);` (Pint). Change settings through `.env` variables, not by editing these files.
- **Where code goes**: Everything shared goes to `simplysmart/mainboard` (Core); zone code goes to its zone package. Do not add new files to the skeleton's `app/` or `config/` without the user's approval.
- **Package config**: Each package ships `config/<package>.php`, merged by `mergeConfigFrom()` in its provider's `register()` and read only via `config('<package>.key')`. Call `env()` only inside that config file (so `config:cache` works). `mergeConfigFrom` merges top-level keys only. Never `publishes()` package config into the skeleton — override it through `.env`. Extend framework config (e.g. a log channel) with `config([...])` in the package provider, never by editing the skeleton's config files.
- **Package layout mirrors Laravel**: `config/`, `routes/`, `resources/views/`, `lang/`, `database/migrations/`, `public/`, with PHP code in `src/` instead of `app/`. Each package loads its own resources in its provider (`loadViewsFrom`, `loadTranslationsFrom`, `loadMigrationsFrom`, routes) under its own namespace (`view('<package>::...')`, `__('<package>::...')`).
- **Inverted override (views and translations)**: The package is the default source; the skeleton only overrides. Laravel looks in the skeleton first — `resources/views/vendor/<package>/` and `lang/vendor/<package>/<locale>/` — and falls back to the package when the file is absent. So the skeleton normally stays empty and everything renders from the packages.
  - An override file is a deliberate, full replacement of that one view or translation file, added only when the user asks for it. Never leave empty or stub override files: an empty file still wins and renders blank.
  - Never bulk-publish package views or translations into the skeleton (`vendor:publish`); copy only the single file being overridden.
  - Config is not overridden with files (see above: `.env` only), and the skeleton's build files (`vite.config.js`, `package.json`) are not edited to wire package assets.
- **Registration**: A package provider is registered only through `extra.laravel.providers` in the package's `composer.json` (PSR-4 + auto-discovery), never in `bootstrap/providers.php`.

## Package Boundaries & Architecture Rules
- **Package roles**: `simplysmart/mainboard` is the Application Core — shared contracts, base classes, DTOs, layouts and UI components, the shared localization middleware and the common zone mechanisms; it is not a zone itself. Zone packages depend on Core contracts. `simplysmart/tools` holds development tooling only.
- **Packages are standalone libraries**: Each package must work without the skeleton. Package code never references `App\...` classes; the skeleton may use packages only through their public API.
- **Contracts First**: Communication between the skeleton and packages, and between packages, goes through interfaces. Each package keeps its own contracts in `src/Contracts/` (e.g. `Simplysmart\Mainboard\Contracts\*`). Depend on another package's contracts, never on its concrete classes; bind implementations in the package's Service Provider.
- **Service Providers**: A Laravel package registers itself via `extra.laravel.providers` in its `composer.json` (auto-discovery; e.g. `Simplysmart\Mainboard\MainboardServiceProvider`). Migrations (`loadMigrationsFrom`), views (`loadViewsFrom`), config (`mergeConfigFrom`) and routes live in the package and are loaded by that provider — never copy them into the skeleton's `database/`, `resources/`, `config/` or `routes/`.
- **Package-level rules**: Each package has its own `CLAUDE.md`; read it before changing that package.

## Multi-Zone Isolation
The application is split into zones. Each zone is an isolated sandbox with its own users table and model, its own guard and its own session, so one browser can be logged in to several zones at once without mixing permissions. The public (guest) zone needs no login. Zones never share users, sessions, permissions or cache.
- **Explicit guards**: Always name the zone's guard: `Auth::guard('<zone-guard>')`, middleware `auth:<zone-guard>` / `guest:<zone-guard>`. Never use `Auth::user()`, `auth()->user()` or `$request->user()` without a guard, and never rely on `auth.defaults.guard`.
- **Session isolation**: Each zone with login uses its own session cookie name (e.g. `<zone>_session`), set by the zone's route-group middleware by overriding `session.cookie` **before** `StartSession` runs. This is what allows concurrent logins. Logging out invalidates only that zone's session. Ensure session cookies use compatible path/domain scopes (`session.path`, `session.domain`) if zones run under different subdomains or URI prefixes, preventing session collisions at the browser level.
- **Drivers**: `SESSION_DRIVER=database` and `CACHE_STORE=database` now, `redis` as the target. Never use `file` for either.
- **Cache isolation**: With the `database` store, prefix every cache key with the zone (`zone:<zone>:...`). Use `Cache::tags(['zone:<zone>', ...])` only after moving to `redis`: the `database`, `file`, `dynamodb` and `storage` stores do not support tags. Zone code never calls a global `Cache::flush()`.
- **Zone packages**: A zone package registers its guard, user provider, session middleware and routes in its own Service Provider (see Package Boundaries above), and reads its zone and guard names from its own config, never hard-coded.

## Routing & URL Conventions
- **URL shape**: `/{locale}/{zone-slug}/{route}`. Folders, namespaces and classes never depend on URLs or zone slugs.
- **Locale mode is decided by config at route registration**: with one enabled locale routes have no locale prefix and the locale is `pl`. With several, the `{locale}` prefix is always present (also for `pl`), constrained with `where` to the enabled locales, and `/` redirects to the default locale. Each route is registered exactly once — never both with and without the prefix.
- **Shared localization middleware** (one for the whole application, not per zone): reads `{locale}`, calls `App::setLocale()` / `Carbon::setLocale()`, sets `URL::defaults(['locale' => ...])`, then `$request->route()->forgetParameter('locale')`. Never rewrite `REQUEST_URI` or the request path.
- **Clean controllers**: Controllers, actions and Form Requests never accept `$locale` or a zone slug as parameters. Read the language from `App::getLocale()`.
- **Configurable slug, stable names**: The zone's Service Provider groups its routes with `prefix` = the slug from the package's own config, `as` = the package's fixed route-name prefix (e.g. `<zone>.`), and the zone middleware (locale, session, `auth:<zone-guard>`). Changing a slug is a config change only and never renames routes; the route-name prefix is not configurable, so `route()` calls never break.
- **Relative route files**: Package route files define relative paths and short names (`->name('dashboard')` → `<zone>.dashboard`). URL prefixes, the name prefix and middleware come only from the Service Provider.
- **Named routes only**: Generate links with `route('<prefix>.<name>')`, never hand-built URLs containing the locale or a slug.
- **Constraints**: Route names are unique across zones (each package has its own name prefix), everything works with `php artisan route:cache`, and no zone slug equals a locale code.

## Skills Ownership
Each skill has exactly one source; never create a second copy of a skill in another repository.
- **`simplysmart/tools`** (`packages/simplysmart/tools/resources/boost/skills/`) owns the shared skills: coding standards (`solid-dry`), documentation (`docs-after-tests`), changelog and releases (`changelog-before-commit`), and any future CI/CD or tooling skills. Edit them there, then run `php artisan boost:update` in the skeleton.
- **Skeleton `.claude/skills/`** is generated by Laravel Boost (`boost.json`: Boost's own skills plus those from `simplysmart/tools`) — do not edit these copies by hand. Skeleton-specific skills (system integration, environment orchestration, scaffolding new packages) are the only ones authored here (Boost leaves skills it did not install untouched).
- **Package `.claude/skills/`** (e.g. `mainboard`) holds only skills for that package's own domain (domain actions, DTOs, package-specific UI components).

## Quality Gates
Run from the directory of the repository you changed (skeleton root or `packages/simplysmart/<package-name>/`):
- `composer lint` — PER style plus `declare(strict_types=1);` in every PHP file (`composer format` fixes both).
- `composer analyse` — PHPStan at level `max` (`phpstan.neon`; Larastan in the skeleton). Fix the cause; do not add ignores or a baseline.
- `composer test` — PHPUnit, where the repository defines it (skeleton, `tools`). Skeleton tests (`tests/Feature/`) cover integration with packages; a package tests its own logic in its own `tests/`.
- After changing a package's `composer.json`, run `composer update simplysmart/<package-name>` in the skeleton so `composer.lock` picks it up.
