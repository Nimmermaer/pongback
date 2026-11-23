..  include:: /Includes.rst.txt

..  _known-problems:

==============
Known problems
==============

Pongback is being prepared for version 1.0. Until then:

*   **Sending** Pingbacks is not implemented yet. On page changes only a
    message is dispatched to the Symfony Messenger.
*   The **backend module**, the **frontend plugins** and the **dashboard
    widget** are being reworked. Use the module :guilabel:`Web > List` to
    approve Pingbacks meanwhile, see :ref:`editors`.
*   **TYPO3 v13**: the route enhancer of the endpoint is added by an event
    listener, because site sets cannot contain route enhancers in v13, see
    :ref:`route-enhancer`.

Report issues at `GitHub <https://github.com/Nimmermaer/pongback/issues>`__.
