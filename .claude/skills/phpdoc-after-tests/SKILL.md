---
name: phpdoc-after-tests
description: "After writing or changing PHP code and running its tests (or verifying it works), write or update the PHPDoc blocks for whatever was touched — classes, methods, properties, interfaces — before considering the task done. Use this as a closing step for any PHP code change in this project, not just when the user explicitly asks for documentation. Skip it only for throwaway scripts or exploratory snippets the user says not to keep."
license: MIT
---

# PHPDoc after tests

Docblocks written before the code is finished go stale the moment a signature changes during testing — a parameter gets renamed, a method starts throwing a new exception, a return type gets narrowed. Writing or updating PHPDoc *after* the code is written and tested means it describes what the code actually does, not what it was expected to do.

## When this runs

As the last step of a PHP change, after:
1. The code is written.
2. It's been tested — existing tests pass, or new ones were added and pass, or (for something untested) you've otherwise confirmed it behaves as intended.

Not before — a docblock written against code that's still in flux just gets rewritten.

## What to document

Walk the diff, not the whole file. For each new or changed:
- **Class / interface** — what it represents or does, in enough detail that the one-sentence-no-"and" test from the `solid-dry` skill still reads true from the docblock alone.
- **Public method / function** — `@param` and `@return` with real types (including generics-style `array<...>` / collection-of shapes where PHP's type system can't express it), `@throws` for exceptions the method can actually throw (not ones it merely passes through unchanged — PHP doesn't require declaring those, but it's worth noting the ones a caller should expect).
- **Property** — when its type or meaning isn't obvious from the name and native type alone (e.g. a string that's actually a slug, a nullable column with a specific meaning for null).
- **Contracts/interfaces** — these are the highest-value target: callers depend on the docblock, not the implementation, to know what an abstraction promises.

Skip documenting what the code already says plainly — a getter returning exactly what its name and return type declare doesn't need a docblock restating that.

## How to write it

Use the `phpdoc-master-pl` skill for the actual docblock format and conventions (Polish-language descriptions, precise `@param`/`@return`/`@throws` typing, IDE-compatible phpDocumentor syntax) — don't improvise a different style here. This skill only decides *when* and *what* to document; that one decides *how*.

## Verify before finishing

- Every `@param` and `@return` type still matches the real signature (re-check after any last-minute edits).
- Every `@throws` entry corresponds to something the method body (or something it calls directly) can actually throw — remove ones that no longer apply, add ones that are new.
- No docblock references a parameter, property, or behavior that was removed in this change.
- The description still reads true: if the code's behavior changed but the words didn't, that's a stale docblock, which is worse than no docblock.
