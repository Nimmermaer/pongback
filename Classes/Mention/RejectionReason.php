<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention;

/**
 * Protocol neutral reasons for rejecting a mention.
 * Each protocol endpoint maps them to its own error representation
 * (Pingback: XML-RPC fault codes, Webmention: HTTP status codes).
 */
enum RejectionReason: string
{
    case InvalidRequest = 'invalid_request';
    /** The source could not be reached (network problem), may be temporary. */
    case SourceNotFound = 'source_not_found';
    /** The source answered 404 or 410: it does not exist (any more). */
    case SourceGone = 'source_gone';
    case NoLinkToTarget = 'no_link_to_target';
    case TargetNotFound = 'target_not_found';
    case TargetNotAllowed = 'target_not_allowed';
    case AlreadyRegistered = 'already_registered';
    case AccessDenied = 'access_denied';
    case UpstreamError = 'upstream_error';
}
