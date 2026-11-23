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
| `Classes/Middleware/DiscoveryMiddleware.php` | `X-Pingback` and `Link: rel="webmention"` headers, `<link rel="pingback">` and `<link rel="webmention">` |
| `Classes/Middleware/PingbackReceiverMiddleware.php`  | Answers POST requests on page type `Constants::PINGBACK_PAGE_TYPE`, before TypoScript rendering |
| `Classes/Pingback/PingbackEndpoint.php` | XML-RPC `pingback.ping`: parameters, rate limit, fault codes |
| `Classes/Webmention/`                  | `WebmentionEndpoint` (quick checks, 202 Accepted), `ReceiveWebmentionHandler` (worker: `MentionReceiver::synchronize()` creates, updates or removes) |
| `Classes/Messaging/MessageQueue.php`   | Always dispatch messages through it: explicit transport, TYPO3 13 would also use the sync `*` route |
| `Classes/Mention/`                     | Protocol neutral receiving (shared by the Pingback and Webmention endpoints): `MentionReceiverInterface`, `TargetResolver` (routing + page visibility), `MentionStorage`, `MentionRateLimiter` |
| `Classes/Mention/Source/`              | Untrusted source handling: `UrlGuard` (SSRF), `SourceFetcher` (limits, redirects, IP pinning), `SourceInspector` (link check, plain text excerpt) |
| `Classes/XmlRpc/XmlRpcCodec.php`       | XML-RPC parsing/encoding (replaces PECL `ext-xmlrpc`)     |
| `Classes/Event/BeforePingbackCreateEvent.php` | Listeners may modify or reject a verified mention before it is stored |
| `Classes/Controller/PingbackController.php` | Frontend plugins (list, latest), cached with the page via the cache tag `tx_pongback_domain_model_pingback` (DataHandler flushes it) |
| `Classes/Listener/`                    | v13 route enhancer listener |
| `Classes/Hooks/PageChangeHook.php`     | DataHandler hook: queues `SendPingbacksMessage` once per changed live page and language |
| `Classes/Sending/`                     | Sending: `SendPingbacksHandler` (Messenger, retries), `LinkExtractor`, `EndpointDiscovery`, `PingbackClient`, `SentPingbackLog` (table `tx_pongback_sent`) |
| `Classes/Html/HtmlDocumentLoader.php`  | Safe HTML parsing shared by receiving and sending |
| `Classes/Controller/Backend/ModerationController.php` | Module `Web > Pongback`: list per page tree, POST approve/hide/delete |
| `Classes/Moderation/`                  | `ModerationRepository` (permission aware queries for module and widget), `PingbackModerator` (DataHandler, so permissions, history and caches are handled by TYPO3) |
| `Classes/Notification/NotifyEditors.php` | Listener of `AfterPingbackCreatedEvent` and `SendingFailedEvent`: emails with login redirect link into the module |
| `Classes/Widget/`                      | Dashboard widget and its button, only registered if EXT:dashboard exists (`Configuration/Services.php`) |
| `Classes/Configuration/PongbackSettings.php` | Typed access to the site settings; sites without the set get `enabled = false` |
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
  extension configuration or TypoScript constants. Values, defaults and types
  live only in the YAML file; `PongbackSettings::KEYS` lists the names (a unit
  test keeps them in sync). Tests take the defaults from the YAML
  (`Tests/Unit/Fixtures/SetSettings`).
- Database tables and columns are created from TCA; there is no
  `ext_tables.sql`. Use TCA options (`max`, types) to shape columns.
- Sites without the set `webicorns/pongback` get no features at all
  (`PongbackSettings::$enabled`).
- Test our code, not TYPO3 or Symfony: no tests that only verify framework
  behaviour.
- There are no installations of earlier versions to migrate: no upgrade
  wizards or backwards compatibility layers are needed before 1.0.0.
