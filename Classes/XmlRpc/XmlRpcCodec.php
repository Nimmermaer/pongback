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

/**
 * Minimal XML-RPC encoder/decoder covering what the Pingback protocol needs.
 *
 * Replaces the PECL "xmlrpc" extension, which is not bundled with PHP 8.
 *
 * @see https://xmlrpc.com/spec.md
 */
final class XmlRpcCodec
{
    /**
     * @return array{methodName: string, params: list<mixed>}
     *
     * @throws \InvalidArgumentException if the payload is not a valid XML-RPC method call
     */
    public function decodeMethodCall(string $xml): array
    {
        $document = $this->loadDocument($xml);
        $root = $document->documentElement;
        if ($root === null || $root->nodeName !== 'methodCall') {
            throw new \InvalidArgumentException('Payload is not an XML-RPC methodCall', 1732300001);
        }

        $methodName = '';
        $params = [];
        foreach ($this->childElements($root) as $child) {
            if ($child->nodeName === 'methodName') {
                $methodName = trim($child->textContent);
            } elseif ($child->nodeName === 'params') {
                foreach ($this->childElements($child) as $param) {
                    if ($param->nodeName !== 'param') {
                        continue;
                    }

                    $value = $this->firstChildElement($param, 'value');
                    $params[] = $value instanceof \DOMElement ? $this->decodeValue($value) : null;
                }
            }
        }

        if ($methodName === '') {
            throw new \InvalidArgumentException('XML-RPC methodCall has no methodName', 1732300002);
        }

        return [
            'methodName' => $methodName,
            'params' => $params,
        ];
    }

    public function encodeResponse(mixed $value): string
    {
        return $this->wrap('<params><param>' . $this->encodeValue($value) . '</param></params>');
    }

    public function encodeFault(int $faultCode, string $faultString): string
    {
        return $this->wrap('<fault>' . $this->encodeValue([
            'faultCode' => $faultCode,
            'faultString' => $faultString,
        ]) . '</fault>');
    }

    private function wrap(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<methodResponse>' . $body . '</methodResponse>';
    }

    private function encodeValue(mixed $value): string
    {
        $encoded = match (true) {
            is_bool($value) => '<boolean>' . ($value ? '1' : '0') . '</boolean>',
            is_int($value) => '<int>' . $value . '</int>',
            is_float($value) => '<double>' . $value . '</double>',
            is_array($value) && array_is_list($value) => '<array><data>'
                . implode('', array_map($this->encodeValue(...), $value))
                . '</data></array>',
            is_array($value) => $this->encodeStruct($value),
            $value === null => '<string></string>',
            default => '<string>' . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</string>',
        };

        return '<value>' . $encoded . '</value>';
    }

    /**
     * @param array<array-key, mixed> $struct
     */
    private function encodeStruct(array $struct): string
    {
        $members = '';
        foreach ($struct as $name => $memberValue) {
            $members .= '<member><name>' . htmlspecialchars((string) $name, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</name>'
                . $this->encodeValue($memberValue) . '</member>';
        }

        return '<struct>' . $members . '</struct>';
    }

    private function decodeValue(\DOMElement $value): mixed
    {
        $typed = $this->firstChildElement($value);
        if (! $typed instanceof \DOMElement) {
            // A value without type element is a string per specification.
            return $value->textContent;
        }

        return match ($typed->nodeName) {
            'int', 'i4', 'i8' => (int) trim($typed->textContent),
            'boolean' => trim($typed->textContent) === '1',
            'double' => (float) trim($typed->textContent),
            'array' => $this->decodeArray($typed),
            'struct' => $this->decodeStruct($typed),
            'nil' => null,
            default => $typed->textContent,
        };
    }

    /**
     * @return list<mixed>
     */
    private function decodeArray(\DOMElement $array): array
    {
        $items = [];
        $data = $this->firstChildElement($array, 'data');
        if (! $data instanceof \DOMElement) {
            return $items;
        }

        foreach ($this->childElements($data) as $item) {
            if ($item->nodeName === 'value') {
                $items[] = $this->decodeValue($item);
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStruct(\DOMElement $struct): array
    {
        $members = [];
        foreach ($this->childElements($struct) as $member) {
            if ($member->nodeName !== 'member') {
                continue;
            }

            $name = $this->firstChildElement($member, 'name');
            $value = $this->firstChildElement($member, 'value');
            if ($name instanceof \DOMElement && $value instanceof \DOMElement) {
                $members[trim($name->textContent)] = $this->decodeValue($value);
            }
        }

        return $members;
    }

    private function loadDocument(string $xml): \DOMDocument
    {
        if (trim($xml) === '') {
            throw new \InvalidArgumentException('Empty XML-RPC payload', 1732300003);
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            // LIBXML_NONET: never fetch external resources; no LIBXML_NOENT, so entities are not substituted.
            $loaded = $document->loadXML($xml, LIBXML_NONET);
            if ($loaded === false) {
                throw new \InvalidArgumentException('Malformed XML-RPC payload', 1732300004);
            }

            if ($document->doctype !== null) {
                throw new \InvalidArgumentException('DOCTYPE declarations are not allowed in XML-RPC payloads', 1732300005);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMNode $node): array
    {
        $elements = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $elements[] = $child;
            }
        }

        return $elements;
    }

    private function firstChildElement(\DOMNode $node, ?string $name = null): ?\DOMElement
    {
        foreach ($this->childElements($node) as $child) {
            if ($name === null || $child->nodeName === $name) {
                return $child;
            }
        }

        return null;
    }
}
