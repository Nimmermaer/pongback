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

*   Every page announces the Pingback and Webmention endpoints of its site
    via HTTP headers and `<link>` tags.
*   The Pingback endpoint (`https://example.com/pingback.ping`) accepts
    XML-RPC calls as defined by the `Pingback specification
    <https://www.hixie.ch/specs/pingback/pingback>`__, the Webmention
    endpoint (`https://example.com/webmention`) form posts as defined by the
    `W3C Webmention recommendation <https://www.w3.org/TR/webmention/>`__.
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
*   Sending Pingbacks to linked websites when a page is changed, in the
    background with retries, see :ref:`sending`
*   Configuration per site with a site set and site settings

*   Backend module :guilabel:`Web > Pongback` for moderation, respecting
    page permissions, and email notification with a link to it
*   Content elements "Pingbacks of this page" and "Latest Pingbacks", cached
    with the page
*   Dashboard widget with the newest Pingbacks and their websites
*   Webmention: receiving with verification in the background, updates and
    deletions of the source; sending with Webmention preferred, see
    :ref:`webmention`

See the `roadmap <https://github.com/Nimmermaer/pongback/milestone/1>`__.

Planned
-------

*   Integration with EXT:news and EXT:blog

..  _requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14
*   PHP 8.2 or newer
*   Optional: EXT:dashboard for the dashboard widget
