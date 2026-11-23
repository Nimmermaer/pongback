..  include:: /Includes.rst.txt

..  _developer:

==============
For developers
==============

..  _developer-endpoint:

The endpoint
============

The endpoint answers `POST` requests with an XML-RPC call of the method
`pingback.ping(sourceURI, targetURI)`. It runs as a middleware before any
TypoScript is rendered, so no `PAGE` object is needed for its page type.
Other request methods are answered with HTTP 405.

..  code-block:: xml
    :caption: Example request to https://example.com/pingback.ping

    <?xml version="1.0"?>
    <methodCall>
        <methodName>pingback.ping</methodName>
        <params>
            <param><value><string>https://blog.example/my-post</string></value></param>
            <param><value><string>https://example.com/article</string></value></param>
        </params>
    </methodCall>

..  _developer-fault-codes:

Fault codes
===========

Errors are XML-RPC faults transported with HTTP 200, as required by the
specification.

..  list-table::
    :header-rows: 1

    *   -   Code
        -   Meaning
    *   -   0
        -   Invalid request, for example a source URL with an internal address
    *   -   16
        -   The source URI does not exist
    *   -   17
        -   The source URI does not contain a link to the target URI
    *   -   32
        -   The target URI does not exist or is not visible
    *   -   33
        -   The target URI cannot be used as target, for example a page of
            another site
    *   -   48
        -   The Pingback has already been registered
    *   -   49
        -   Access denied, for example by the rate limit or an event listener
    *   -   50
        -   The source could not be retrieved (upstream error)
    *   -   -32700, -32601, -32602
        -   Not well formed XML, unknown method, invalid parameters

..  _developer-event:

Modify or reject Pingbacks
==========================

The PSR-14 event
:php:`\Webicorns\Pongback\Event\BeforePingbackCreateEvent` is dispatched
after a Pingback has been verified and before it is stored. Listeners can
change the record, for example publish Pingbacks of trusted sites
immediately, or reject the Pingback. The reason of a rejection is sent back
to the remote site, so do not reveal internal details.

..  code-block:: php
    :caption: EXT:my_sitepackage/Classes/EventListener/PingbackPolicy.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MySitepackage\EventListener;

    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
    use Webicorns\Pongback\Mention\RejectionReason;

    #[AsEventListener]
    final class PingbackPolicy
    {
        public function __invoke(BeforePingbackCreateEvent $event): void
        {
            $source = $event->getMention()->source;

            if (str_contains($source, 'spam.example')) {
                $event->reject(RejectionReason::AccessDenied, 'Not accepted.');
                return;
            }

            if (str_starts_with($source, 'https://trusted.example/')) {
                $event->getPingback()->setHidden(false);
            }
        }
    }

Besides the record, the event provides the incoming mention
(:php:`getMention()`: source, target, client IP, user agent) and the
resolved target (:php:`getTarget()`: site, page ID, language ID).
A rejection stops the event propagation.

..  _developer-event-created:

React to new Pingbacks
======================

The PSR-14 event
:php:`\Webicorns\Pongback\Event\AfterPingbackCreatedEvent` is dispatched
after a Pingback has been stored. It provides the record UID (:php:`$uid`),
the record (:php:`$pingback`), the incoming mention (:php:`$mention`) and
the resolved target (:php:`$target`). The email notification of the
extension is a listener of this event.

..  code-block:: php
    :caption: EXT:my_sitepackage/Classes/EventListener/PingbackToChat.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MySitepackage\EventListener;

    use TYPO3\CMS\Core\Attribute\AsEventListener;
    use Webicorns\Pongback\Event\AfterPingbackCreatedEvent;

    #[AsEventListener]
    final class PingbackToChat
    {
        public function __invoke(AfterPingbackCreatedEvent $event): void
        {
            // e.g. post $event->pingback->getSourceLink() to a chat
        }
    }

..  _developer-event-sending-failed:

React to sending problems
=========================

:php:`\Webicorns\Pongback\Event\SendingFailedEvent` is dispatched after
the Pingbacks and Webmentions of a page were sent, if linked pages could not
be notified for good. It provides the site, the page ID, the page URL and
the problems (:php:`$problems`: the result per linked URL). The email
notification of the extension is a listener of this event.

..  _developer-templates:

Templates
=========

Plugins
    Override the Fluid templates with the site settings
    `plugin.tx_pongback.view.templateRootPath`, `partialRootPath` and
    `layoutRootPath`. The templates are `Pingback/List.html` and
    `Pingback/Latest.html`, both render the partial `List/Item.html`.

Email
    The email templates are `PongbackPingbackReceived` and
    `PongbackSendingFailed` (HTML and text) in
    :file:`EXT:pongback/Resources/Private/Templates/Email/`. Override them
    with a template of the same name in a path of
    :php:`$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths']` with a
    higher key than `1732600000`.

..  _developer-webmention:

Protocol neutral core
=====================

Receiving is implemented independent of the Pingback protocol in the
namespace :php:`\Webicorns\Pongback\Mention`
(:php:`MentionReceiverInterface`). The Pingback endpoint translates XML-RPC
to an :php:`IncomingMention` and rejection reasons to fault codes, the
Webmention endpoint form posts and HTTP status codes. Webmentions use
:php:`MentionReceiverInterface::synchronize()`, which also updates and
removes stored mentions.
