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

namespace Webicorns\Pongback\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * A received mention, stored on the target page (pid). Title and excerpt are plain text, never HTML.
 */
class Pingback extends AbstractEntity
{
    protected bool $hidden = false;

    protected string $sourceTitle = '';

    protected string $excerpt = '';

    protected string $sourceLink = '';

    protected string $targetLink = '';

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getSourceTitle(): string
    {
        return $this->sourceTitle;
    }

    public function setSourceTitle(string $sourceTitle): void
    {
        $this->sourceTitle = $sourceTitle;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): void
    {
        $this->excerpt = $excerpt;
    }

    public function getSourceLink(): string
    {
        return $this->sourceLink;
    }

    public function setSourceLink(string $sourceLink): void
    {
        $this->sourceLink = $sourceLink;
    }

    public function getTargetLink(): string
    {
        return $this->targetLink;
    }

    public function setTargetLink(string $targetLink): void
    {
        $this->targetLink = $targetLink;
    }

}
