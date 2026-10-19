---
name: changelog-before-commit
description: "Before any git commit in a simplysmart project or package: add the CHANGELOG.md [Unreleased] entry for user-visible changes, preview the release with simplysmart/tools (vendor/bin/version-release --dry-run) and release only on explicit request, and confirm docs-after-tests already ran on the diff. Use whenever a commit is imminent, not only when the changelog is mentioned."
license: MIT
---

# Changelog before commit

Documentation (doc comments, `docs/`, README, CONTRIBUTING, CLAUDE.md) is already handled by `docs-after-tests`. This step only adds what belongs to the commit itself.

## Which repository

The skeleton and every package in `packages/<vendor>/<package>/` are separate Git repositories with their own `CHANGELOG.md` and `VERSION`. Run the steps below once per repository that has changes, and commit each one in its own repository.

## Steps

1. **Read the diff being committed** — `git diff --staged`, or the working diff if nothing is staged.
2. **Docs check.** If the diff changes code but no doc comment or document covering it, run `docs-after-tests` first. Don't redo it when it already ran.
3. **CHANGELOG entry** — only for user-visible changes (feature, requirement, setup step, behavior, fixed bug). Skip refactors, renames, test-only changes, formatting. Add it under `## [Unreleased]` in `### Added` / `Changed` / `Deprecated` / `Removed` / `Fixed` / `Security` (Keep a Changelog), in Polish, describing what the project now does, not which files changed. If an entry for the same change is still unreleased, amend it instead of adding a contradicting one.
4. **Bump awareness.** `### Removed` or an entry starting with `**BREAKING**` forces a MAJOR bump; `Added` / `Deprecated` → MINOR; `Changed` / `Fixed` / `Security` → PATCH. Don't file a non-breaking change under `Removed` (e.g. dropping an internal file) — it would force a MAJOR release.
5. **Release point?** If `[Unreleased]` is non-empty, preview the release from the root of the project where `simplysmart/tools` is installed:

   ```bash
   vendor/bin/version-release --dry-run                                     # this project
   vendor/bin/version-release --dry-run --root=packages/<vendor>/<package>  # a package
   ```

   Report the previewed version. Run it **without** `--dry-run` only when the user explicitly asks for a release — it really rewrites `VERSION` and `CHANGELOG.md`. Then commit both files together with the code. A MAJOR bump stops and asks for `--major-confirm` — confirm with the user that the change really breaks compatibility before passing it. Otherwise leave `[Unreleased]` open and say so explicitly.

There is one release implementation, `simplysmart/tools`; never copy it into a project or package.

This skill does not run `git commit`. Stage and commit only when asked, after showing `git status` / `git diff`.
