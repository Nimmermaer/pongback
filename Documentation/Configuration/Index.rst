..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

All options are configured per site. There are no TypoScript constants to
set.

..  _site-set:

Include the site set
====================

The extension comes with the site set `webicorns/pongback`. It provides the
TypoScript of the plugins, the route enhancer of the endpoint and the site
settings. Add it to your site configuration in
:guilabel:`Site Management > Sites` or directly:

..  code-block:: diff
    :caption: config/sites/my-site/config.yaml (diff)

     base: 'https://example.com/'
     rootPageId: 1
    +dependencies:
    +  - webicorns/pongback

See also: `TYPO3 Explained, Using a site set as dependency in a site
<https://docs.typo3.org/permalink/t3coreapi:site-sets-usage>`__.

..  _route-enhancer:

The endpoint URL
================

The endpoint is the page type `1709405325110` of the root page. The site set
maps it to the URL `https://example.com/pingback.ping`.

TYPO3 v14
    The route enhancer is part of the site set and applied automatically.

TYPO3 v13
    Site sets cannot contain route enhancers in v13. The extension adds the
    route enhancer to all sites instead, as long as the extension setting
    :guilabel:`includePongbackYaml` is enabled (default) in
    :guilabel:`Admin Tools > Settings > Extension Configuration > pongback`.

A route enhancer `PageTypeSuffix` in your own site configuration takes
precedence and is merged with the one of the extension.

..  _site-set-settings:

Site settings
=============

Adjust the settings in :guilabel:`Site Management > Settings`, category
:guilabel:`Pongback`.

..  typo3:site-set-settings:: PROJECT:/Configuration/Sets/Pongback/settings.definitions.yaml
    :name: main-set
    :type:
    :Label: max=30
    :default: max=10
