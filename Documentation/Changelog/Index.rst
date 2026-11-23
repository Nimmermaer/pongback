..  include:: /Includes.rst.txt

..  _changelog:

=========
Changelog
=========

..  _changelog-unreleased:

Unreleased
==========

A rewrite for TYPO3 13.4 and 14. Installations of earlier versions need to be
set up again: there is no migration of records or configuration.

Breaking
--------

*   Requires TYPO3 13.4 or 14 and PHP 8.2. The PECL extension `xmlrpc` is no
    longer required.
*   EXT:reactions is no longer used. The site configuration field
    `pingback_identifier` and the reaction type are removed.
*   The TypoScript is only available via the site set `webicorns/pongback`,
    the static template and the TypoScript constants are removed.
*   Configuration moved from the extension configuration to
    :ref:`site settings <site-set-settings>`. Only `includePongbackYaml`
    (TYPO3 v13) remains in the extension configuration.
*   Plugins are content elements (`CType`) instead of `list_type` plugins.
*   Database: the fields `ping_client`, `ping_resource` and
    `serialized_information` are replaced by `source_title` and `excerpt`.
*   New Pingbacks are hidden by default (moderation).
*   The extension has no :file:`ext_tables.sql` any more, TYPO3 creates the
    tables from TCA.
*   Sites without the site set `webicorns/pongback` get no Pingback
    features: no discovery, no endpoint, no sending.
*   The backend modules "Pongback Admin" and its AJAX toggle are replaced by
    the module :guilabel:`Web > Pongback`. The Pingback table is hidden in
    :guilabel:`Web > List`.
*   The cache `tx_pongback_cache` is removed, plugins are cached with the
    page.
*   The Page TSconfig for the new content element wizard, the list module
    and the dashboard templates is removed.

Features
--------

*   Pingback endpoint as middleware with all fault codes of the
    specification, see :ref:`developer-fault-codes`.
*   Verification of target and source page, rate limit, duplicate detection,
    see :ref:`security`.
*   PSR-14 event :php:`BeforePingbackCreateEvent` can reject Pingbacks, see
    :ref:`developer-event`.
*   Discovery header and link tag can be switched off per site.
*   Module :guilabel:`Web > Pongback` to approve, hide and delete Pingbacks
    of a page tree, see :ref:`editors-module`.
*   Email notification with a link into the module, see
    :ref:`editors-email`.
*   PSR-14 event :php:`AfterPingbackCreatedEvent`, see
    :ref:`developer-event-created`.
*   Dashboard widget with the linking websites in its own group.
*   Sending Pingbacks via the Symfony Messenger with retries and a log of
    sent Pingbacks, see :ref:`sending`. EXT:webhooks is no longer needed.

Security
--------

*   The fetched source page is no longer written to a file in the web root.
*   Server side request forgery protection when fetching the source page,
    TLS verification enabled.
*   Title and excerpt are stored as plain text and escaped on output.
