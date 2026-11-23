..  include:: /Includes.rst.txt

..  _webmention:

==========
Webmention
==========

`Webmention <https://www.w3.org/TR/webmention/>`__ is the W3C successor of
Pingback: the same idea, a simple form POST instead of XML-RPC. Pongback
receives and sends both. Received Webmentions are moderated and shown like
Pingbacks; module, widget and email show the protocol.

..  _webmention-receiving:

Receiving
=========

Every site with the site set has the endpoint
`https://example.com/webmention` (page type `1709405325111`, see
:ref:`route-enhancer`). Every page announces it with the HTTP header
`Link: <https://example.com/webmention>; rel="webmention"` and a
`<link rel="webmention">` tag, controlled by the same discovery settings as
Pingback.

The endpoint accepts `POST` requests with the form fields `source` and
`target`:

..  code-block:: bash
    :caption: Example request

    curl -i https://example.com/webmention \
        -d source=https://blog.example/post \
        -d target=https://example.com/article

..  uml:: /Images/Plantuml/receive-webmention.plantuml
    :caption: Receiving a Webmention, verified later by the worker

The endpoint checks right away that both are `http(s)` URLs, that they
differ, that the target is a page of this site visible to visitors, and the
rate limit (`pongback.receive.rateLimit`). It answers:

202 Accepted
    The Webmention is queued and will be verified.

400 Bad Request
    Invalid parameters or a target that is not a page of this site.

429 Too Many Requests
    The rate limit of the client is reached.

The source is fetched and verified by the worker of the message queue, see
:ref:`sending-worker`, with the same protection as for Pingbacks
(:ref:`security-source`):

*   A new source that links to the target creates a mention, hidden for
    moderation unless `pongback.receive.autoPublish` is enabled. The events
    :ref:`BeforePingbackCreateEvent <developer-event>` and
    :ref:`AfterPingbackCreatedEvent <developer-event-created>` and the email
    notification apply as for Pingbacks.
*   A Webmention for a known source and target updates title and excerpt;
    the moderation state stays.
*   If the source is gone (404 or 410) or no longer links to the target,
    the mention is removed.
*   Network and server problems of the source are retried after 5 minutes
    and after 1 hour.

Switch receiving off with the site setting `pongback.receive.webmention`.
The Pingback endpoint is not affected.

..  _webmention-sending:

Sending
=======

When a page changes (see :ref:`sending`), Pongback looks for a Webmention
endpoint first: in the `Link` header of the linked page, then in the first
`<link>` or `<a>` element with `rel="webmention"`. Only if there is none, a
Pingback is sent.

*   Webmentions are sent again whenever the page changes, so the receiver
    can update the mention. Pingbacks are sent once.
*   If a link is removed from the page, the linked page receives one more
    Webmention, so it can remove the mention.
*   Any `2xx` answer counts as sent. Other client errors are final, `429`,
    server and network errors are retried.

Disable the site setting `pongback.send.webmention` to send Pingbacks only.
