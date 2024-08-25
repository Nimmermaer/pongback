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

use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;

class Pingback extends AbstractEntity
{
    protected array $validationError = [];

    protected bool $hidden = false;

    protected string $pingClient = '';

    protected string $pingResource = '';

    #[Validate([
        'validator' => NotEmptyValidator::class,
    ])]
    protected string $serializedInformation = '';

    #[Validate([
        'validator' => NotEmptyValidator::class,
    ])]
    protected string $sourceLink = '';

    #[Validate([
        'validator' => NotEmptyValidator::class,
    ])]
    protected string $targetLink = '';

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function getPingClient(): string
    {
        return $this->pingClient;
    }

    public function setPingClient(string $pingClient): void
    {
        $this->pingClient = $pingClient;
    }

    public function getPingResource(): string
    {
        return $this->pingResource;
    }

    public function setPingResource(string $pingResource): void
    {
        $this->pingResource = $pingResource;
    }

    public function getSerializedInformation(): string
    {
        return $this->serializedInformation;
    }

    public function setSerializedInformation(string $serializedInformation): void
    {
        $this->serializedInformation = $serializedInformation;
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

    public function getValidationError(): array
    {
        return $this->validationError;
    }

    public function setValidationError(array $validationError): void
    {
        $this->validationError = $validationError;
    }
}
