..  include:: /Includes.rst.txt

..  _editors:

===========
For editors
===========

..  _editors-moderation:

Approve Pingbacks
=================

New Pingbacks are stored hidden on the page they link to, unless the site
setting `pongback.receive.autoPublish` is enabled.

To approve a Pingback, open the page in the module :guilabel:`Web > List`
and unhide its record.

To reject a Pingback, keep it hidden or delete it. A hidden Pingback blocks
further submissions of the same source and target; after deleting it, the
remote site can send it again.

..  note::
    A dedicated backend module with approval and email notification is
    being reworked for version 1.0, see :ref:`known-problems`.

..  _editors-what-is-stored:

What is stored
==============

Source link
    URL of the page that links to your page.

Source title
    Title of the linking page, as plain text.

Target link
    URL of your page that is linked.

Excerpt
    Up to 300 characters of text around the link in the linking page, as
    plain text.
