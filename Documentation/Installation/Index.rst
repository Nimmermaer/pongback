..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  _installation-composer:

Install Pongback with Composer
==============================

..  code-block:: bash

    composer require webicorns/pongback

See also `Installing extensions, TYPO3 Getting started
<https://docs.typo3.org/permalink/t3start:installing-extensions>`__.

..  _installation-classic:

Install Pongback in Classic mode
================================

Download the extension from the
`TYPO3 Extension Repository (TER) <https://extensions.typo3.org/extension/pongback>`__
and install it in the module :guilabel:`System > Extensions`.

..  _installation-database:

Update the database
===================

Pongback stores Pingbacks in the table `tx_pongback_domain_model_pingback`.
Create it with the database analyzer in :guilabel:`Admin Tools > Maintenance`
or on the command line:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

..  _installation-next:

Next steps
==========

Add the site set to your site, see :ref:`configuration`.
