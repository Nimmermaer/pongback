..  include:: /Includes.rst.txt

..  _security:

========
Security
========

A Pingback is a request from an unknown party on the internet. Pongback
therefore checks every Pingback before anything is stored.

..  _security-target:

Target page
===========

*   The target must be a page of the site whose endpoint was called.
*   The page must be visible to anonymous visitors: hidden, scheduled and
    access protected pages are rejected, as well as folders, spacers and
    recyclers.

..  _security-source:

Source page
===========

The source page is fetched to check that it really links to the target.
To prevent server side request forgery:

*   Only `http` and `https` URLs on the default ports are accepted, without
    user names or passwords.
*   The host must resolve to public IP addresses only. Private, loopback,
    link local, carrier grade NAT and IPv4 mapped addresses are rejected.
*   Redirects are followed manually (at most 3) and every hop is checked
    again. The connection is pinned to the checked IP address.
*   The request has a timeout of 10 seconds, at most 1 MiB is read and only
    HTML documents are accepted.

..  _security-storage:

Storage and output
==================

*   Title and excerpt of the source page are stored as plain text. Scripts,
    styles and markup are removed.
*   Duplicates are rejected, also while a Pingback is still waiting for
    approval.
*   The number of Pingbacks per client IP and hour is limited (site setting
    `pongback.receive.rateLimit`, default 20).
*   XML-RPC requests are parsed without external entities; documents with a
    DOCTYPE declaration are rejected.

..  _security-webmention:

Webmention endpoint
===================

*   The endpoint only checks the request and the target page and answers
    `202 Accepted`; the source is fetched later by the worker, with the same
    protection as above. Senders cannot use the endpoint to make your server
    wait for slow or internal hosts.
*   The rate limit applies to Webmentions as well.

..  _security-sending:

Sending
=======

*   Linked pages and their Pingback endpoints are requested with the same
    protection as source pages: public hosts and default ports only, every
    redirect checked, connection pinned to the checked IP address.
*   The endpoint is called without following redirects.

..  _security-backend:

Backend
=======

*   Approving, hiding and deleting are POST requests with the token of the
    backend route and are done by the DataHandler as the logged in user:
    table, field and page permissions apply.
*   Module and dashboard widget only show Pingbacks on pages the user may
    see.
*   The link in the notification email only opens the module after logging
    in; it does not change anything by itself.

..  _security-report:

Report a security issue
=======================

Please do not report security issues in the public issue tracker. Follow the
`TYPO3 Extension Security Policy
<https://typo3.community/contribute/teams-committees/security/extension-security-policy>`__
and contact the TYPO3 Security Team at security@typo3.org.
