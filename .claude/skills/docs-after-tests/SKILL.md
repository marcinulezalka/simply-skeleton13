---
name: docs-after-tests
description: "Closing step for any code change in this project, in any language: once the code works (tests pass or behavior is verified), document it in one pass over the diff — doc comments in the language's own standard, plus docs/, README.md, CONTRIBUTING.md and CLAUDE.md where the change affects them. Skip for throwaway snippets."
license: MIT
---

# Docs after tests

Documentation written before the code settles describes intent, not behavior, and gets rewritten. Run this once, after tests pass, in a single pass over `git diff` — the change is still fresh, so nothing has to be re-derived later at commit time.

## 1. Doc comments in the code

Walk the diff, not whole files. Document new or changed classes, functions, methods and non-obvious properties/constants. Contracts (interfaces, abstract classes, public API) first — callers rely on the comment, not the implementation.

| Language | Standard | Must cover |
| --- | --- | --- |
| PHP | PHPDoc (phpDocumentor) | `@param` / `@return` only where native types can't say it (`list<string>`, array shapes, generics); `@throws` for exceptions a caller should expect |
| JavaScript | JSDoc | `@param {type}`, `@returns`, `@throws` |
| TypeScript | TSDoc | `@param`, `@returns`, `@throws` — no types in tags, the signature has them |
| Python | docstrings, PEP 257 (Google style sections) | `Args:`, `Returns:`, `Raises:` |
| Shell | Google Shell Style Guide | file header (purpose, usage); per function: `Globals`, `Arguments`, `Outputs`, `Returns` |
| CSS / SCSS | `/** */` block per component or section | purpose, modifiers, dependencies on other components |
| SQL / migrations | `--` comments | why the change, non-obvious constraints, irreversible steps |
| Blade / HTML templates | `{{-- --}}` / `<!-- -->` | expected variables and their types |

For a language not listed, use its dominant community standard.

Conventions for every language:
- Descriptions in Polish; tags and keywords stay in the standard's form. Match the density of the surrounding code.
- Don't restate what the name and signature already say — a comment that repeats the code is noise to maintain.
- Every tag matches the final code: parameter names, types, thrown exceptions. Nothing references removed behavior. A stale comment is worse than none.

## 2. Project documentation

If the project has `docs/README.md`, read it first — its tables say which document owns which topic and when to update it, plus what is deliberately left undocumented. Otherwise check `README.md`, `CONTRIBUTING.md` and `CLAUDE.md` (commands, requirements, rules an agent relies on). Update only the documents the change actually affects; each fact lives in one place and others link to it. `CHANGELOG.md` is not part of this step — `changelog-before-commit` handles it.

## 3. Check

Re-read the diff of what you wrote against the code, not against the task description. If nothing in steps 1–2 applied, say so in one line — it's a deliberate result, not a skipped step.
