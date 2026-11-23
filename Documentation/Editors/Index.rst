..  include:: /Includes.rst.txt

..  _editors:

===========
For editors
===========

..  _editors-moderation:

Approve Pingbacks
=================

New Pingbacks are stored hidden on the page they link to and wait for
approval, unless the site setting `pongback.receive.autoPublish` is enabled.

..  _editors-module:

The module Web > Pongback
-------------------------

Open :guilabel:`Web > Pongback` and select a page in the page tree. The
module lists the Pingbacks of this page and its subpages: pending ones
first, then the newest.

Approve
    Shows the Pingback on the website.

Hide
    Removes an approved Pingback from the website again.

Delete
    Deletes the Pingback. It can be restored with the recycler.

A pending or hidden Pingback blocks further submissions of the same source
and target. After deleting it, the linking site can send it again.

The Pingback records are not shown in the module :guilabel:`Web > List`.

..  _editors-email:

Email notification
------------------

If the site setting `pongback.notification.emailTo` contains one or more
email addresses, they are notified about every new Pingback and Webmention,
and about linked pages that could not be notified, see
:ref:`sending-problems`. The link in
the email opens the Pingback in the module :guilabel:`Web > Pongback`,
after logging in if necessary. The link itself changes nothing: approving
needs a click in the module.

..  _editors-permissions:

Permissions
-----------

Editors only see Pingbacks on pages they may see. To approve, hide or delete
them, they need:

*   access to the module :guilabel:`Web > Pongback`,
*   the tables :guilabel:`Pingback` in "Tables (listing)" and
    "Tables (modify)" of their backend group,
*   the excluded field :guilabel:`Pingback: Hidden`,
*   permission to edit the content of the page the Pingback is stored on.

..  _editors-plugins:

Show Pingbacks on the website
=============================

Add one of the content elements from the group :guilabel:`Plugins`:

Pingbacks of this page
    The approved Pingbacks of the page the element is placed on, newest
    first, with pagination.

Latest Pingbacks
    The newest approved Pingbacks of the whole site.

The number of Pingbacks per page (or in total for "Latest Pingbacks") is
set in the tab :guilabel:`Pingback Configuration` of the content element
(default: 5).
Both show the title of the linking page with a link, the date and the
excerpt. New, approved or hidden Pingbacks update the pages showing them.

..  _editors-dashboard:

Dashboard widget
================

With EXT:dashboard installed, the widget :guilabel:`Latest Pingbacks`
(group :guilabel:`Pingbacks`) shows the newest Pingbacks on pages you may
see, with the linking website, marks pending ones and links to the module.

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
