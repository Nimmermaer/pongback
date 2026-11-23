# Contributing to Pongback

These standards apply to everybody working on this extension: humans and AI
agents alike.

## Supported versions

| Component | Versions                    |
|-----------|-----------------------------|
| TYPO3     | 13.4 LTS and 14             |
| PHP       | 8.2, 8.3, 8.4               |

Every change must work on **both** TYPO3 versions. If an API differs between
v13 and v14, branch on `Typo3Version` or use the API that exists in both. Do
not introduce v14-only APIs until v13 support is dropped.

## Workflow

- Work is planned as GitHub issues assigned to a milestone (e.g. `1.0.0`).
- Branch from `main`: `feature/<issue>-<short-name>`, `bugfix/<issue>-<short-name>`
  or `task/<issue>-<short-name>`.
- Open a pull request against `main` and reference the issue (`Resolves: #2`).
- CI must be green before merging.

## Commit messages

Follow the TYPO3 commit message format:

```
[TYPE] Short summary in imperative mood (max. 72 chars)

Optional body explaining *why* the change is needed.

Resolves: #2
```

| Type         | Use for                                         |
|--------------|-------------------------------------------------|
| `[FEATURE]`  | New functionality                               |
| `[BUGFIX]`   | Fixing broken behaviour                         |
| `[SECURITY]` | Security fixes                                  |
| `[TASK]`     | Refactoring, maintenance, tooling, dependencies |
| `[DOCS]`     | Documentation only                              |
| `[!!!]`      | Prefix for breaking changes, e.g. `[!!!][TASK]` |

## Coding standards

- PHP code follows **PER-CS 2.0** plus the rules in `.php-cs-fixer.dist.php`.
  Formatting of all other files is defined in `.editorconfig`.
- Every PHP file starts with `declare(strict_types=1);`.
- Prefer `final` and `readonly` classes; use constructor injection instead of
  `GeneralUtility::makeInstance()` wherever the class is a DI service.
- Use getters/setters on domain models, never access properties directly.
- No `$GLOBALS['TSFE']`, no `list_type` plugins, no internal TYPO3 hooks
  (marked `@internal`), no PECL extensions such as `ext-xmlrpc`.
- Use PSR-14 events instead of hooks where TYPO3 offers them.
- Magic numbers (page types, cache identifiers) belong in
  `Classes/Utility/Constants.php`.

## Security rules

Pingbacks are untrusted input from the internet. Therefore:

- Never write remote content to the file system.
- Outgoing HTTP requests need a timeout and TLS verification; never request
  private or internal addresses on behalf of a remote party.
- Parse XML only via `XmlRpcCodec` (no external entities, no DOCTYPE).
- Escape all output; never store HTML from remote sources unfiltered.

## Quality checks

Install the dev dependencies with `composer install`, then run:

| Command                    | Purpose                               |
|----------------------------|---------------------------------------|
| `composer ci:cgl`          | Coding guidelines (dry run)           |
| `composer fix:cgl`         | Fix coding guidelines                 |
| `composer ci:rector`       | Rector incl. TYPO3 Rector (dry run)   |
| `composer fix:rector`      | Apply Rector refactorings             |
| `composer ci:phpstan`      | Static analysis                       |
| `composer ci:tests:unit`   | Unit tests                            |
| `composer ci:tests:functional` | Functional tests (SQLite)         |
| `composer ci:tests:mutation` | Mutation testing, min. 90 % covered MSI |

The same checks run in GitHub Actions (`.github/workflows/ci.yml`) for every
supported TYPO3/PHP combination.

## Documentation

- Changes visible to integrators, editors or developers need an update of
  the manual in `Documentation/` and an entry in
  `Documentation/Changelog/Index.rst`.
- The CI renders the manual and fails on warnings (see AGENTS.md for the
  command).

## Tests

- New logic needs unit tests in `Tests/Unit/`, mirroring the `Classes/`
  structure.
- Bug fixes come with a test that fails without the fix.
- Use PHPUnit attributes (`#[Test]`), not annotations.
- Code that needs TYPO3 (routing, database, DI, middlewares) is tested with
  functional tests in `Tests/Functional/`, against TYPO3 v13 and v14.
- Tests must detect bugs, not only execute code: Infection must report a
  covered MSI of at least 90 %.
