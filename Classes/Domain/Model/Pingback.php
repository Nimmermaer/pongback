<?php

declare(strict_types=1);

namespace PHTH\Pongback\Domain\Model;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Michael Blunck <michael.blunck@phth.de>, PHTH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Extbase\Annotation as Extbase;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;

/**
 * Class Pingback
 * @package PHTH\Pongback\Domain\Model
 */
class Pingback extends AbstractEntity
{
    public array $validationErrors = [];

    #[Extbase\Validate([
        'validator' => NotEmptyValidator::class,
    ])]
    protected string $sourceLink;

    protected string $pingClient;

    protected string $pingRessource;

    #[Extbase\Validate([
        'validator' => NotEmptyValidator::class,
    ])]
    protected string $targetLink;

    #[Extbase\Validate([
        'validator' => NotEmptyValidator::class,
    ])]
    protected string $serializedInformation;

    protected array $validationError;

    protected bool $hidden = false;

    public function getSourceLink(): string
    {
        return $this->sourceLink;
    }

    public function setSourceLink(string $sourceLink): void
    {
        $this->sourceLink = $sourceLink;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    public function isHidden()
    {
        return $this->getHidden();
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

    public function getPingRessource(): string
    {
        return $this->pingRessource;
    }

    public function setPingRessource(string $pingRessource): void
    {
        $this->pingRessource = $pingRessource;
    }

    public function addValidationError($validationError): void
    {
        $this->validationErrors[] = $validationError;
    }

    public function setValidationErrors($validationErrors): void
    {
        $this->validationErrors = $validationErrors;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getTargetLink(): string
    {
        return $this->targetLink;
    }

    public function setTargetLink(string $targetLink): void
    {
        $this->targetLink = $targetLink;
    }

    public function getSerializedInformation(): string
    {
        return $this->serializedInformation;
    }

    public function setSerializedInformation(string $serializedInformation): void
    {
        $this->serializedInformation = $serializedInformation;
    }

    /**
     * information changed in object
     */
    public function serializeInformation(mixed $param): void
    {
        $this->setSerializedInformation(serialize($param));
    }
}
