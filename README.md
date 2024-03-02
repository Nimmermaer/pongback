![PHP Version](https://img.shields.io/badge/PHP-8.2-blue)
![TYPO3 Version](https://img.shields.io/badge/TYPO3-12-orange)
![Extension Status](https://img.shields.io/badge/Status-Alpha-lightblue)
![Version](https://img.shields.io/badge/Version-0.0.1-blue)

[TOC]

# TYPO3 Extension - Pongback

**Description**: This TYPO3 extension, developed by Webicorns, enables the sending and receiving of Pingbacks within your TYPO3 website.

## Features

- **Receiving Pingbacks**: Pongback allows your TYPO3 website to receive Pingbacks from other websites.
- **Storage and Backend Module**: It provides a backend module for storing and displaying received Pingbacks.
- **Sending Pingbacks**: You can use Pongback to send Pingbacks automatically when linking to other websites.

## Future Features

Pongback has exciting future plans to enhance its functionality:

- **Display Linked Page Pingbacks**: Pongback will display Pingbacks received on linked pages.
- **Email Notification and Backend Module**: A notification and approval process via email and the backend module will be implemented.
- **Visibility Check**: Pingbacks will only be sent if the linked page is visible.
- **Send on Page Publication**: Pingbacks will be sent only upon the publication of the linked page.
- **Latest Pingbacks Dashboard**: Pongback will introduce a dashboard feature that displays the latest Pingbacks along with their source websites.
- **Extension Compatibility**: Pongback aims to support popular TYPO3 extensions such as EXT:news and EXT:blog.

> Stay tuned for these exciting updates to Pongback!

## Quick Start

1) Install the extension:
```bash
    composer require webicorns/pongback
```

2) Configuration
    + Include the basic TypoScript
    + Look at extension configuration for further configurations
      ![Backend configuration](../Resources/Public/Documentation/backend_configuration.png)

