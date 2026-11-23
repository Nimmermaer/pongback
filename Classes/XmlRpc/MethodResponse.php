<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\XmlRpc;

final readonly class MethodResponse
{
    private function __construct(
        public mixed $value,
        public ?int $faultCode,
        public string $faultString,
    ) {}

    public static function success(mixed $value): self
    {
        return new self($value, null, '');
    }

    public static function fault(int $faultCode, string $faultString): self
    {
        return new self(null, $faultCode, $faultString);
    }

    public function isFault(): bool
    {
        return $this->faultCode !== null;
    }
}
