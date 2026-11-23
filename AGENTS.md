# AGENTS.md

Briefing for AI coding agents working on this repository. Read
[CONTRIBUTING.md](CONTRIBUTING.md) first: its standards are binding for
agents as well.

## What this is

Pongback is a TYPO3 extension (`webicorns/pongback`, extension key
`pongback`) that receives, moderates, displays and sends
[Pingbacks](https://www.hixie.ch/specs/pingback/pingback).
Supported: **TYPO3 13.4 and 14, PHP 8.2+**.

The roadmap lives in GitHub issues under the milestone `1.0.0`
(M1 #2 … M5 #6). Check the relevant issue before starting work and keep
its checklist in sync.

## Architecture at a glance

| Path                                   | Responsibility                                            |
|----------------------------------------|-----------------------------------------------------------|
| `Classes/Middleware/PingbackDiscoveryMiddleware.php` | `X-Pingback` header and `<link rel="pingback">` |
| `Classes/Middleware/PingbackReceiverMiddleware.php`  | Answers POST requests on page type `Constants::PINGBACK_PAGE_TYPE`, before TypoScript rendering |
| `Classes/Pingback/PingbackEndpoint.php` | XML-RPC `pingback.ping`: parameters, rate limit, fault codes |
| `Classes/Mention/`                     | Protocol neutral receiving (also for a future Webmention endpoint): `MentionReceiverInterface`, `TargetResolver` (routing + page visibility), `MentionStorage`, `MentionRateLimiter` |
| `Classes/Mention/Source/`              | Untrusted source handling: `UrlGuard` (SSRF), `SourceFetcher` (limits, redirects, IP pinning), `SourceInspector` (link check, plain text excerpt) |
| `Classes/XmlRpc/XmlRpcCodec.php`       | XML-RPC parsing/encoding (replaces PECL `ext-xmlrpc`)     |
| `Classes/Event/BeforePingbackCreateEvent.php` | Listeners may modify or reject a verified mention before it is stored |
| `Classes/Controller/PingbackController.php` | Frontend plugins (list, latest) |
| `Classes/Listener/`                    | DataHandler hook for sending, v13 route enhancer listener |
| `Classes/Message/PingbackMessage.php`  | Messenger/webhook message on page changes                 |
| `Classes/Controller/Backend/`          | Backend module (`Web > Pongback`)                         |
| `Classes/Widget/`                      | Dashboard widget, only registered if EXT:dashboard exists |
| `Classes/Configuration/PongbackSettings.php` | Typed site settings incl. fallback defaults            |
| `Configuration/Sets/Pongback/`         | Site set: TypoScript, site settings (`settings.definitions.yaml`), labels |
| `Configuration/Sets/Pongback/route-enhancers.yaml` | Route enhancer for the endpoint; native in v14, merged by `EnhanceSiteConfigurationListener` in v13 |

## Commands

```bash
composer install            # dev dependencies into .Build/
composer ci:cgl             # php-cs-fixer (dry run)
composer ci:rector          # rector + ssch/typo3-rector (dry run)
composer ci:phpstan         # static analysis
composer ci:tests:unit      # unit tests
composer ci:tests:functional # functional tests (SQLite, no database server needed)
composer ci:tests:mutation  # Infection, fails below 90 % covered MSI
```

Run all of them before you commit. Green tests are not enough: new logic must keep the
covered mutation score (MSI) of Infection at 90 % or above. If a tool cannot be installed in your
environment, say so explicitly in your summary instead of claiming it passed.

## Documentation

The manual in `Documentation/` (reStructuredText) follows the TYPO3 guide
"How to document": https://docs.typo3.org/permalink/h2document:start.
Render it and fail on every warning before committing changes there:

```bash
mkdir -p Documentation-GENERATED-temp
docker run --rm --pull always -v $(pwd):/project \
  ghcr.io/typo3-documentation/render-guides:latest \
  --config=Documentation --no-progress --minimal-test
```

- Check every statement against `Classes/` and `Configuration/`.
- Never remove an anchor (`..  _name:`), also not when deleting a section.
- No symbolic links inside `Documentation/`, and no AGENTS.md there.

## Releases

Releases are published to TER by `.github/workflows/publish.yml` when a tag
like `1.2.3` is pushed. Before tagging, set the version in `composer.json`
(`extra.typo3/cms.version`), `ext_emconf.php` and `Documentation/guides.xml`,
e.g. with `tailor set-version 1.2.3`, and add the changelog entry.
Never put the TER token anywhere but the repository secret `TYPO3_API_TOKEN`.

## Rules for agents

- Keep changes compatible with **both** TYPO3 13 and 14. When unsure, look
  up the API in both versions instead of relying on memory.
- Configure `ssch/typo3-rector` only up to the lowest supported TYPO3
  version (`Typo3LevelSetList::UP_TO_TYPO3_13`).
- Do not suppress PHPStan errors via ignores or baseline; fix the cause.
- Follow the security rules in CONTRIBUTING.md; this extension processes
  untrusted requests from the internet.
- Use the TYPO3 commit message format (`[TASK] …`, `[BUGFIX] …`) and
  reference the issue (`Resolves: #<n>` or `Related: #<n>`).
- Do not commit `.Build/`, `composer.lock` or cache files.
- Do not edit generated or vendor code.
- Site related options are site settings (`settings.definitions.yaml`), not
  extension configuration or TypoScript constants. Keep the defaults in sync
  with `PongbackSettings::DEFAULTS` (covered by a unit test).
- There are no installations of earlier versions to migrate: no upgrade
  wizards or backwards compatibility layers are needed before 1.0.0.
