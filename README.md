![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)
![TYPO3 Version](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014-orange)
![Extension Status](https://img.shields.io/badge/Status-Beta-lightblue)
![Version](https://img.shields.io/badge/Version-0.10.0-blue)
[![CI](https://github.com/Nimmermaer/pongback/actions/workflows/ci.yml/badge.svg)](https://github.com/Nimmermaer/pongback/actions/workflows/ci.yml)

# TYPO3 Extension - Pongback

Pongback, developed by Webicorns, lets your TYPO3 website receive, moderate,
display and send [Pingbacks](https://www.hixie.ch/specs/pingback/pingback)
and [Webmentions](https://www.w3.org/TR/webmention/):
when another website links to one of your pages, it can notify you, and the
link shows up on your page after approval.

📖 **Manual:** https://docs.typo3.org/p/webicorns/pongback/main/en-us/
(source in [`Documentation/`](Documentation/Index.rst))

**Requirements:** TYPO3 13.4 LTS or 14, PHP 8.2 or newer.
EXT:dashboard is optional (dashboard widget).

## Features

### Ready

- **Receiving Pingbacks** via an XML-RPC endpoint per site
  (`https://<your-site>/pingback.ping`), implementing the Pingback
  specification including all fault codes.
- **Receiving Webmentions** at `https://<your-site>/webmention`: quick
  checks and `202 Accepted`, the source is verified in the background; a
  Webmention sent again updates the mention, a deleted source removes it.
- **Discovery**: every page announces both endpoints via HTTP headers
  (`X-Pingback`, `Link: <…>; rel="webmention"`) and `<link>` tags (both can
  be switched off per site).
- **Verification**: a Pingback is only stored if the target is a page of the
  same site that is visible to anonymous visitors, and the source page really
  links to it.
- **Moderation by default**: new Pingbacks are stored hidden and need to be
  approved (or published immediately, if configured).
- **Spam and abuse protection**: duplicates are rejected, a rate limit per
  client IP applies, and listeners can reject Pingbacks (see below).
- **Security**: the source page is fetched with protection against server side
  request forgery (public hosts and default ports only, every redirect checked,
  timeout and size limit). Title and excerpt of the source are stored as plain
  text only.
- **Sending** to linked websites when a page or its content is changed:
  Webmention if the linked page offers it, otherwise Pingback. In the
  background via the Symfony Messenger (worker:
  `vendor/bin/typo3 messenger:consume doctrine`), with retries and a log per
  page. Webmentions are sent again on changes and for removed links.
- **Moderation** in the backend module *Web > Pongback*: approve, hide and
  delete the Pingbacks of a page tree, limited to the pages an editor may
  edit.
- **Email notification** about new Pingbacks with a link into the module.
- **Content elements** "Pingbacks of this page" (with pagination) and
  "Latest Pingbacks" of the site, cached with the page.
- **Dashboard widget** with the newest Pingbacks and their websites.
- **Site set** with site settings: everything is configured per site in the
  backend, no TypoScript constants or extension settings needed.

### Planned after 1.0.0

- Integration with EXT:news and EXT:blog.
- Advanced spam filters.

## Quick Start

1) Install the extension:

   ```bash
   composer require webicorns/pongback
   ```

2) Add the site set **"Pongback: Pingback endpoint and plugins"**
   (`webicorns/pongback`) to your site configuration. It provides the
   TypoScript, the route enhancer for `pingback.ping` and the site settings.
   Sites without the set get no Pingback features.

   In TYPO3 v13 the route enhancer is added to all sites by the extension
   setting `includePongbackYaml` (enabled by default), as v13 does not support
   route enhancers in site sets.

3) Adjust the settings per site in *Site Management > Settings > Pongback*:

   | Setting                                       | Default              |
   |-----------------------------------------------|----------------------|
   | `pongback.discovery.httpHeader`               | `true`               |
   | `pongback.discovery.metaTag`                  | `true`               |
   | `pongback.receive.autoPublish`                | `false` (moderation) |
   | `pongback.receive.rateLimit` (per IP and hour, `0` = off) | `20`     |
   | `pongback.receive.webmention`                 | `true`               |
   | `pongback.send.enabled`                       | `true`               |
   | `pongback.send.webmention` (prefer Webmention) | `true`              |
   | `pongback.send.tables`                        | `pages, tt_content`  |
   | `pongback.notification.emailTo` / `emailFrom` | empty                |
   | `plugin.tx_pongback.view.*RootPath`           | extension paths      |
   | `plugin.tx_pongback.persistence.storagePid`   | `0`                  |

4) To send Pingbacks and Webmentions and to verify received Webmentions, run
   the message queue worker regularly, e.g. by cron:

   ```bash
   # crontab of the web server user, e.g. sudo crontab -u www-data -e
   */5 * * * * /usr/bin/php /var/www/example.com/vendor/bin/typo3 messenger:consume doctrine --time-limit=240 --quiet
   ```

   Without the worker nothing is sent. Alternatives (TYPO3 scheduler,
   supervisor/systemd) and how to check it: see the manual, chapter
   "Sending Pingbacks", section "Run the worker".

## For developers

### Reacting to incoming Pingbacks

`Webicorns\Pongback\Event\BeforePingbackCreateEvent` is dispatched after a
Pingback has been verified and before it is stored. Listeners can change the
record or reject the Pingback; the reason is sent back to the remote site:

```php
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
use Webicorns\Pongback\Mention\RejectionReason;

#[AsEventListener]
final class BlockSpamDomains
{
    public function __invoke(BeforePingbackCreateEvent $event): void
    {
        if (str_contains($event->getMention()->source, 'spam.example')) {
            $event->reject(RejectionReason::AccessDenied, 'Not accepted.');
        }
    }
}
```

`AfterPingbackCreatedEvent` is dispatched after a Pingback has been stored;
the email notification is a listener of it.

### Quality

Every change is checked in CI against TYPO3 13 and 14 with PHP 8.2 to 8.4:
coding guidelines, Rector, PHPStan, unit tests, functional tests (SQLite) and
mutation testing with Infection (at least 90 % covered MSI).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for coding standards, commit message
format and quality checks, and [AGENTS.md](AGENTS.md) for AI coding agents.
