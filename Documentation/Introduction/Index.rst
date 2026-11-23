..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

A Pingback is a notification from one website to another: "My page links to
your page." The receiving site checks the claim and can show the link, for
example below an article.

Pongback adds this to TYPO3:

*   Every page announces the Pingback endpoint of its site via the
    `X-Pingback` HTTP header and a `<link rel="pingback">` tag.
*   The endpoint (`https://example.com/pingback.ping`) accepts XML-RPC calls
    as defined by the `Pingback specification
    <https://www.hixie.ch/specs/pingback/pingback>`__.
*   A Pingback is only stored if the linking page really links to a visible
    page of your site. New Pingbacks need to be approved by default.

..  _features:

Features
========

Ready
-----

*   Receiving Pingbacks, including all fault codes of the specification
*   Discovery via HTTP header and HTML link tag, can be switched off per site
*   Verification of target page (same site, visible to visitors) and source
    page (contains a link to the target)
*   Moderation by default, duplicate detection, rate limit per client IP
*   Protection against server side request forgery when fetching the source
    page, plain text storage of title and excerpt
*   PSR-14 event to modify or reject Pingbacks
*   Configuration per site with a site set and site settings

Being reworked for version 1.0
------------------------------

*   Sending Pingbacks to linked websites when a page is published
*   Backend module for moderation, email notification with approval
*   Frontend plugins and dashboard widget showing the Pingbacks

See the `roadmap <https://github.com/Nimmermaer/pongback/milestone/1>`__.

Planned
-------

*   `Webmention <https://www.w3.org/TR/webmention/>`__ support
*   Integration with EXT:news and EXT:blog

..  _requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14
*   PHP 8.2 or newer
*   Optional: EXT:dashboard for the dashboard widget
