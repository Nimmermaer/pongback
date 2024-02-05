<?php

declare(strict_types=1);

namespace Webicorns\Pongback\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Pingback extends AbstractEntity
{
    public array $validationError = [];
    public bool $hidden = false;
    public string $pingClient = '';
    public string $pingResource = '';
    public string $serializedInformation = '';
    public string $sourceLink = '';
    public string $targetLink = '';
}
