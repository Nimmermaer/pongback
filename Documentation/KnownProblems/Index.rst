..  include:: /Includes.rst.txt

..  _known-problems:

==============
Known problems
==============

Pongback is being prepared for version 1.0. Until then:

*   **Sending**: pages that become visible by their start time are not
    detected, save them again to send their Pingbacks. Sending needs a
    worker for the message queue, see :ref:`sending-worker`. The worker must
    be able to request the pages of the site via their public URL.
*   **TYPO3 v13**: the route enhancer of the endpoint is added by an event
    listener, because site sets cannot contain route enhancers in v13, see
    :ref:`route-enhancer`.

Report issues at `GitHub <https://github.com/Nimmermaer/pongback/issues>`__.
