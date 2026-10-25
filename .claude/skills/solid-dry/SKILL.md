---
name: solid-dry
description: "Apply SOLID, DRY and the simplysmart PHP 8.4 standards (strict_types, enums/constants instead of magic values, readonly) whenever writing, refactoring or reviewing code — new classes, services, controllers, jobs or any non-trivial function, not only when SOLID/DRY is named; ends with a verification pass over the diff."
license: proprietary
---

# SOLID & DRY

These principles are proxies for one question: if a fact or a requirement changes tomorrow, how many places do I have to touch, and how much do I risk breaking something unrelated? Use them as a lens while writing code, then spend a minute re-checking the diff against them before calling the task done.

In a Laravel project, the `laravel-best-practices` skill (installed by Laravel Boost) — its `architecture.md` already covers extracting business operations into action/service classes and depending on contracts at boundaries — read that for the Laravel-specific mechanics (constructor injection, binding contracts in a service provider). This skill is the cross-cutting checklist; it doesn't repeat that detail. In a modular project, follow the boundaries in `CLAUDE.md`: packages depend on each other only through contracts (`src/Contracts/`).

## While writing

### Single Responsibility

Describe the class or function in one sentence. If the sentence needs "and," look for the seam — the point where one part would change for a reason the other part doesn't care about (e.g. "renders a template" changing for a different reason than "shells out to a certificate tool"). Split along that seam, not along arbitrary line counts.

### Open/Closed

Ask whether adding the next variant (a new provider, a new output format, a new notification channel) means editing this class's body, or just adding a new implementation of something it already depends on. Add that seam only where a second variant is already real or clearly coming — an interface with one implementation and no plausible second one is speculative cost, not design.

### Liskov Substitution

Any implementation of an interface or abstract class should be swappable without the caller adding special cases. If calling code ends up doing `instanceof` checks, or one implementation throws for inputs the others accept silently, the abstraction is lying about what it promises.

### Interface Segregation

Check whether every implementer of an interface actually needs every method on it. A fat interface forces unrelated implementers to fake methods they don't support (`throw new RuntimeException('not supported')` is the tell). Split interfaces along what callers actually use, not along what one concrete class happens to offer.

### Dependency Inversion

Check where a class's low-level dependencies come from: constructed internally (`new SomeHttpClient()`, raw filesystem calls, a static facade doing I/O) or received from outside. High-level policy (business rules, orchestration) should depend on an abstraction it owns; the low-level detail implements that abstraction, never the other way around. Prefer the framework's existing seam (e.g. an injected `Filesystem` instead of global `file_*` calls) over inventing a new interface when one already exists.

### DRY

Ask: if this fact changes tomorrow, how many files would need to change today? More than one place encoding the same decision — a version string, a path format, a validation rule, a block of boilerplate text — is DRY debt; make one of them the source and have the others reference it. Code that merely *looks* similar but encodes an independent decision is not a DRY violation — don't force a shared abstraction over coincidence; that trades a small duplication for a worse coupling.

## PHP standards

The project requires PHP ^8.4 (`composer.json`). These are the mechanical conventions; the principles above decide the structure.

- **`declare(strict_types=1);`** at the top of every PHP file — implicit coercion hides exactly the type bugs a signature is supposed to catch.
- **Types everywhere:** parameters, return types, and properties. Use PHPDoc only for what the type system can't say (`list<string>`, array shapes, generics).
- **No magic values.** A string or number that encodes a decision (a status, a section name, a default timezone) becomes an enum case or a named constant — that's DRY applied to literals. A fixed set of options is an enum, not a list of strings. `simplysmart/admiro` (`src/Tools/Release/`) shows the pattern: `ReleaseBump` enum, class constants such as `Changelog::UNRELEASED_TITLE`.
- **Immutability by default:** `readonly` classes or properties for value objects and results, constructor promotion for dependencies. Mutable state needs a reason.
- **`match` over `switch`** when mapping a value to a result — it's an expression, compares strictly, and throws on an unhandled case instead of silently falling through.
- **PER Coding Style (PSR-12 successor) via Laravel Pint `per` preset, PSR-4 autoloading:** one class per file, namespace mirrors the directory. Run `composer lint` (`composer format` fixes style and adds missing `strict_types`).
- **No traits as a DRY fix.** Shared behavior goes into an injected collaborator (a service, a value object), not a trait — a trait copies code into each class and hides the dependency.

## After writing: verification pass

Before treating a change as finished, re-read the diff (not just the final file) and check:

1. **One sentence per unit.** Can every new/changed class or function still be described without "and"? If not, that's new SRP debt from this change specifically.
2. **Dependencies arrive, not get constructed.** For anything that touches disk, network, a process, the clock, or other global state: did it arrive via constructor/parameter, or get instantiated inline inside business logic?
3. **Grep for repetition this change introduced.** Search for literals, strings, or small blocks this diff added in more than one place. If the same value appears twice, it should come from one constant, config entry, or template variable, not be retyped.
4. **PHP standards hold.** Every new PHP file starts with `declare(strict_types=1);`, every signature is fully typed, and no decision is encoded as a bare string or number.
5. **Run what already exists.** If tests cover the touched area, run them. If the change adds real branching and nothing covers it, a short test is worth more than the review itself — SOLID violations are usually also untestable code, so a test that's awkward to write is a signal, not just busywork.
6. **Run the project's linter and static analysis** — `composer lint` and `composer analyse` (PHPStan, level `max`); fix the cause, never add ignores or a baseline — formatting and static analysis catch mechanical issues a principle-level read misses, and they're cheap to run.

## What not to do

- Don't add an interface or a new layer for something with exactly one implementation and no plausible second one — note it as a candidate seam in a comment or the PR description if genuinely likely, but don't build it speculatively.
- Don't split a small, cohesive class into pieces just to tick SOLID boxes — SRP is about reasons to change, not file count.
- Don't chase zero duplication for code that looks alike today but represents independent decisions that may diverge — three similar lines beat one wrong shared abstraction.
