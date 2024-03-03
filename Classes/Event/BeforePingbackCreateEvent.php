<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Webicorns\Pongback\Event;

use Webicorns\Pongback\Domain\Model\Pingback;

final class BeforePingbackCreateEvent
{
    public function __construct(
        private Pingback $pingback
    ) {
    }

    public function getPingback(): Pingback
    {
        return $this->pingback;
    }

    public function setPingback(Pingback $pingback): void
    {
        $this->pingback = $pingback;
    }
}
