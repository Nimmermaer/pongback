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

/**
 * Class Pingback
 * @package PHTH\Pongback\Domain\Model
 */
class Pingback extends AbstractEntity
{
    public $validationErrors;

    /**
     * sourceLink
     *
     * @var \string
     * @Extbase\Validate("NotEmpty")
     */
    protected $sourceLink;

    /**
     * pingClient
     *
     * @var \string
     */
    protected $pingClient;

    /**
     * pingRessource
     *
     * @var \string
     */
    protected $pingRessource;

    /**
     * targetLink
     *
     * @var \string
     * @Extbase\Validate("NotEmpty")
     */
    protected $targetLink;

    /**
     * serializedInformation
     *
     * @var \string
     * @Extbase\Validate("NotEmpty")
     */
    protected $serializedInformation;

    /**
     * validation error
     *
     * @var \array
     */
    protected $validationError;

    /**
     * @var boolean
     */
    protected $hidden = false;

    /**
     * Returns the sourceLink
     *
     * @return \string
     */
    public function getSourceLink()
    {
        return $this->sourceLink;
    }

    /**
     * Sets the sourceLink
     *
     * @param \string $sourceLink
     */
    public function setSourceLink($sourceLink): void
    {
        $this->sourceLink = $sourceLink;
    }

    /**
     * @return boolean
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * @return boolean
     */
    public function isHidden()
    {
        return $this->getHidden();
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    /**
     * Returns the pingClient
     *
     * @return \string
     */
    public function getPingClient()
    {
        return $this->pingClient;
    }

    /**
     * Sets the pingClient
     *
     * @param \string $pingClient
     */
    public function setPingClient($pingClient): void
    {
        $this->pingClient = $pingClient;
    }

    /**
     * Returns the pingRessource
     *
     * @return \string
     */
    public function getPingRessource()
    {
        return $this->pingRessource;
    }

    /**
     * Sets the pingRessource
     *
     * @param \string $pingRessource
     */
    public function setPingRessource($pingRessource): void
    {
        $this->pingRessource = $pingRessource;
    }

    /**
     * Adds the validationError
     *
     * @return \string
     */
    public function addValidationError($validationError): void
    {
        $this->validationErrors[] = $validationError;
    }

    /**
     * Sets the validationErrors
     *
     * @return \array
     */
    public function setValidationErrors($validationErrors): void
    {
        $this->validationErrors = $validationErrors;
    }

    /**
     * Returns the validationErrors
     *
     * @return \array
     */
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }

    /**
     * Returns the targetLink
     *
     * @return \string
     */
    public function getTargetLink()
    {
        return $this->targetLink;
    }

    /**
     * Sets the targetLink
     *
     * @param \string $targetLink
     */
    public function setTargetLink($targetLink): void
    {
        $this->targetLink = $targetLink;
    }

    /**
     * Returns the serializedInformation
     *
     * @return \string
     */
    public function getSerializedInformation()
    {
        return $this->serializedInformation;
    }

    /**
     * Sets the serializedInformation
     *
     * @param \string $serializedInformation
     */
    public function setSerializedInformation($serializedInformation): void
    {
        $this->serializedInformation = $serializedInformation;
    }

    /**
     * information changed in object
     * @param mixed $param
     */
    public function seralizeInformation(mixed $param): void
    {
        $this->setSerializedInformation(serialize($param));
    }
}
