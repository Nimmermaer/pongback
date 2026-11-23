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
#.  For every link the endpoint of the linked page is looked up: Webmention
    first, see :ref:`webmention-sending`, otherwise Pingback (`X-Pingback`
    header or `<link rel="pingback">`), and the linked page is notified.

Every Pingback is sent only once per page and link, Webmentions again when
the page changes. The result, including the protocol, is stored as record
:guilabel:`Sent Pingback` on the page and can be viewed in the module
:guilabel:`Web > List`.

..  _sending-worker:

Run the worker
==============

Saving a page only puts a message into the queue (database table
`sys_messenger_messages`). Pingbacks and Webmentions are sent, and received
Webmentions verified, only when a worker of the Symfony Messenger processes
the queue. Without a worker nothing is sent and the messages stay in the
table.

The worker is the console command `messenger:consume` with the transport
`doctrine`:

..  code-block:: bash
    :caption: Composer mode

    vendor/bin/typo3 messenger:consume doctrine

..  code-block:: bash
    :caption: Classic mode

    typo3/sysext/core/bin/typo3 messenger:consume doctrine

Choose one of the following ways to run it.

..  _sending-worker-cron:

Cron job
--------

The simplest way on most servers. Open the crontab of the user that runs
the website (the owner of :file:`var/`, often `www-data`), for example with
`sudo crontab -u www-data -e`, and add:

..  code-block:: bash
    :caption: crontab

    */5 * * * * /usr/bin/php /var/www/example.com/vendor/bin/typo3 messenger:consume doctrine --time-limit=240 --quiet

*   `*/5 * * * *` starts the worker every five minutes.
*   `--time-limit=240` stops it after four minutes, so two runs never
    overlap.
*   Use absolute paths: the PHP command line binary of the same version as
    the website (`which php`) and the project directory.

On shared hosting, add the same command as cron job in the hosting panel.

..  _sending-worker-scheduler:

TYPO3 scheduler
---------------

With EXT:scheduler installed, add a scheduler task for console commands,
select the command `messenger:consume`, enter `doctrine` as argument and set
the option `time-limit` to `240`, and run it every five minutes. The
scheduler itself needs a cron job for `vendor/bin/typo3 scheduler:run`, see
the `scheduler manual <https://docs.typo3.org/c/typo3/cms-scheduler/main/en-us/>`__.

..  _sending-worker-daemon:

Permanent process
-----------------

For immediate processing, keep the worker running with a process manager
like supervisor or systemd and restart it regularly, for example with
`--time-limit=3600`. See `TYPO3 Explained, Message bus
<https://docs.typo3.org/permalink/t3coreapi:message-bus>`__.

..  _sending-worker-check:

Check the worker
----------------

Run the worker once by hand and watch the output:

..  code-block:: bash

    vendor/bin/typo3 messenger:consume doctrine --limit=10 --time-limit=60 -vv

If the table `sys_messenger_messages` keeps growing, the worker does not
run. Sent Pingbacks and Webmentions appear as records :guilabel:`Sent
Pingback` on the page in :guilabel:`Web > List`.

..  _sending-worker-none:

Without a worker
----------------

What works without a worker:

Receiving Pingbacks
    Works. The endpoint verifies and stores Pingbacks right away.

Receiving Webmentions
    The endpoint answers `202 Accepted`, but the source is only verified,
    and the Webmention stored, by the worker.

Sending Pingbacks and Webmentions
    Does not work. Saving only queues the message.

Moderation, plugins and dashboard widget work with what is stored.

If you cannot run a worker, for example on hosting without cron jobs, route
the messages to the synchronous transport `default` of TYPO3. They are then
processed immediately within the request:

..  code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][
        \Webicorns\Pongback\Sending\SendPingbacksMessage::class
    ] = 'default';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][
        \Webicorns\Pongback\Webmention\ReceiveWebmentionMessage::class
    ] = 'default';

The price:

*   Saving a page in the backend waits until all linked pages are notified,
    up to 10 seconds per unresponsive site.
*   The sender of a Webmention waits until the source is verified.
*   Retries happen right away instead of after 5 minutes and 1 hour.

A worker remains the recommended setup.

..  _sending-worker-transport:

Other transports
----------------

The messages are sent to the transport `doctrine` unless you route them
elsewhere, then run the worker for that transport:

..  code-block:: php
    :caption: config/system/additional.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][
        \Webicorns\Pongback\Sending\SendPingbacksMessage::class
    ] = 'my_transport';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][
        \Webicorns\Pongback\Webmention\ReceiveWebmentionMessage::class
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

..  _sending-problems:

Sending problems
================

Linked pages that could not be notified, rejected or failed, are listed in
the module :guilabel:`Web > Pongback` below the received Pingbacks of the
selected page tree, with the answer of the linked site and the number of
attempts. The dashboard widget shows how many there are.

If the site setting `pongback.notification.emailTo` is set, the recipients
get one email per page when linked pages could not be notified for good:

*   rejected by the linked site (reported once, not again on every save),
*   still failing after the last attempt.

..  _sending-disable:

Switch off sending
==================

Disable the site setting `pongback.send.enabled` to send no Pingbacks for a
site. Sites without the site set `webicorns/pongback` never send Pingbacks.
