..  include:: /Includes.rst.txt

..  _sending:

=================
Sending Pingbacks
=================

When a page is changed, Pongback notifies the websites it links to. Sending
runs in the background, saving a page in the backend is not slowed down.

..  _sending-how:

How it works
============

#.  An editor saves a page or a record on it, for example a content element.
    Which tables trigger sending is set by the site setting
    `pongback.send.tables` (default: `pages, tt_content`). Changes in a
    workspace count when they are published.
#.  Pongback queues one message per page and language in the message queue
    `doctrine` (database table `sys_messenger_messages`).
#.  A worker processes the message: it requests the page as anonymous
    visitors see it. Hidden, scheduled and access protected pages, folders
    and spacers are skipped.
#.  The links are taken from the `<main>` element of the page, or from
    `<article>` if there is no `<main>`, otherwise from `<body>`. Links to
    any site of this TYPO3 installation are ignored, at most 50 links are
    used per page.
#.  For every link the Pingback endpoint of the linked page is looked up
    (`X-Pingback` header or `<link rel="pingback">`) and `pingback.ping` is
    called.

Every Pingback is sent only once per page and link. The result is stored as
record :guilabel:`Sent Pingback` on the page and can be viewed in the module
:guilabel:`Web > List`.

..  _sending-worker:

Run the worker
==============

Messages are only processed by a worker of the Symfony Messenger. Run it
regularly, for example as cron job every five minutes:

..  code-block:: bash
    :caption: crontab

    */5 * * * * /path/to/project/vendor/bin/typo3 messenger:consume doctrine --time-limit=240

Or keep it running permanently with a process manager like supervisor or
systemd. See `TYPO3 Explained, Message bus
<https://docs.typo3.org/permalink/t3coreapi:message-bus>`__.

The messages are sent to the transport `doctrine` unless you route them
elsewhere:

..  code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][
        \Webicorns\Pongback\Sending\SendPingbacksMessage::class
    ] = 'my_transport';

..  _sending-results:

Results and retries
===================

Sent
    The linked site accepted the Pingback.

Already registered
    The linked site already knew the Pingback.

No endpoint
    The linked page does not accept Pingbacks.

Rejected
    The linked site refused the Pingback, or the link or endpoint is not a
    public `http(s)` URL. It is not sent again.

Failed
    A temporary problem: network error, server error or a generic fault.
    Pongback tries again after 5 minutes and after 1 hour (3 attempts in
    total). Saving the page again starts a new attempt.

..  _sending-disable:

Switch off sending
==================

Disable the site setting `pongback.send.enabled` to send no Pingbacks for a
site. Sites without the site set `webicorns/pongback` never send Pingbacks.
