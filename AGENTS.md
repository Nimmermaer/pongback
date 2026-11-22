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
| `Classes/Middleware/PingbackReceiverMiddleware.php`  | Receives XML-RPC calls on page type `Constants::PINGBACK_PAGE_TYPE` via EXT:reactions |
| `Classes/XmlRpc/XmlRpcCodec.php`       | XML-RPC parsing/encoding (replaces PECL `ext-xmlrpc`)     |
| `Classes/Controller/PingbackController.php` | Frontend plugins (list, latest) and `pingback()` processing |
| `Classes/Listener/`                    | DataHandler hook for sending, enrichment of received pingbacks, site config |
| `Classes/Message/PingbackMessage.php`  | Messenger/webhook message on page changes                 |
| `Classes/Controller/Backend/`          | Backend module (`Web > Pongback`)                         |
| `Classes/Widget/`                      | Dashboard widget, only registered if EXT:dashboard exists |
| `Classes/Upgrades/`                    | Upgrade wizards                                           |
| `Configuration/Sets/Pongback/`         | Site set (preferred way to include TypoScript)            |
| `Configuration/Sets/Pongback/route-enhancers.yaml` | Route enhancer for the endpoint; native in v14, merged by `EnhanceSiteConfigurationListener` in v13 |

## Commands

```bash
composer install            # dev dependencies into .Build/
composer ci:cgl             # php-cs-fixer (dry run)
composer ci:rector          # rector + ssch/typo3-rector (dry run)
composer ci:phpstan         # static analysis
composer ci:tests:unit      # unit tests
```

Run all four before you commit. If a tool cannot be installed in your
environment, say so explicitly in your summary instead of claiming it passed.

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
