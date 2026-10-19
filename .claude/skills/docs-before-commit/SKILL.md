---
name: docs-before-commit
description: "Before running a git commit for this project — or whenever asked to commit — check the staged/pending diff against README.md and CHANGELOG.md at the project root, and update whichever of them the change actually affects, so the commit carries docs and behavior together. Also run the version-release step when CHANGELOG.md's [Unreleased] section ends up non-empty. Use whenever a commit is imminent, not only when documentation is explicitly requested."
license: MIT
---

# Docs before commit

A commit is the checkpoint other people (and future-you) read back from. If `README.md` or `CHANGELOG.md` don't move with the change that's about to be committed, they drift quietly — nobody notices until someone's debugging something these docs were supposed to explain. Tying the doc update to the commit, rather than to a separate "update docs" task, is what keeps them honest.

## Before committing

1. **Look at the actual diff being committed**, not just the latest task description — `git diff --staged` (or the full working diff if nothing's staged yet). Check what changed; don't assume, since a change often does more or less than it set out to.
2. **Decide if it's user-visible.** A new feature, a changed requirement, a new setup step, a changed permission boundary — user-visible. A pure refactor, an internal rename, a test-only change, or a formatting pass usually isn't. `CHANGELOG.md` is for the former; padding it with internal churn makes it less useful, not more.
3. **Update each file the change actually touches:**
   - `README.md` — if what the project does, how to run it, its requirements, or a command, path, or step it documents changed. A command block that's now wrong is worse than no command block, so re-run it mentally against the new code before trusting it.
   - `CHANGELOG.md` — add an entry under `## [Unreleased]`, in the right `### Added` / `### Changed` / `### Fixed` / `### Removed` section (create the section if it's not there yet), written from the perspective of what the project now does, not which files were edited.
4. **Re-read what you wrote against the code**, not just against the task description — a doc edit that restates intent rather than actual behavior is the same staleness problem one commit later.
5. **If `[Unreleased]` ended up non-empty after step 3, decide whether this commit is a release point.** Run `vendor/bin/version-release` — it reads `[Unreleased]`, picks the SemVer bump (major/minor/patch) itself, and promotes it to a dated `## [x.y.z]` section, bumping `VERSION` to match. Include the updated `VERSION`/`CHANGELOG.md` in the same commit as the code they describe. A `major` bump stops and asks for `--major-confirm` instead of applying silently — treat that pause as real: confirm it's actually a breaking change before passing the flag. If several commits should land under one eventual release, skip this step and leave `[Unreleased]` open — say so explicitly, so it's a deliberate choice rather than a missed step.

## What this doesn't do

This skill prepares the docs — and, via `vendor/bin/version-release`, the version number — that go *into* the commit. It doesn't run `git commit` itself. Committing still follows the normal confirmation flow: don't stage or commit without being asked to in this instance, and show what's about to be committed (`git status`/`git diff`) same as any other commit.

Skip this for a commit with no user-visible effect, and skip the changelog entry specifically for anything that isn't worth a line in release notes — not every commit needs one.
