..  include:: /Includes.rst.txt

..  _start:

========
Pongback
========

:Extension key:
    pongback

:Package name:
    webicorns/pongback

:Version:
    |release|

:Language:
    en

:Author:
    Webicorns & Contributors

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

Receive, moderate, display and send
`Pingbacks <https://www.hixie.ch/specs/pingback/pingback>`__ with TYPO3:
when another website links to one of your pages, it notifies your site, and
the link can be shown on your page after approval.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What Pingbacks are and what this extension does with them.

    ..  card:: :ref:`Installation <installation>`

        Install the extension in Composer based and Classic mode TYPO3
        installations.

    ..  card:: :ref:`Configuration <configuration>`

        Include the site set `webicorns/pongback` and adjust the site
        settings.

    ..  card:: :ref:`For editors <editors>`

        Approve or reject received Pingbacks.

    ..  card:: :ref:`For developers <developer>`

        Endpoint, fault codes and the PSR-14 event to modify or reject
        Pingbacks.

    ..  card:: :ref:`Security <security>`

        What is checked before a Pingback is stored.

..  toctree::
    :hidden:
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Editors/Index
    Sending/Index
    Developer/Index
    Security/Index
    KnownProblems/Index
    Changelog/Index

..  toctree::
    :hidden:

    Sitemap
