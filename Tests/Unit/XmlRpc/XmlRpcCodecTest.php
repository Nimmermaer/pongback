<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\XmlRpc;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

final class XmlRpcCodecTest extends TestCase
{
    private XmlRpcCodec $subject;

    protected function setUp(): void
    {
        $this->subject = new XmlRpcCodec();
    }

    #[Test]
    public function decodesPingbackMethodCall(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <methodCall>
              <methodName>pingback.ping</methodName>
              <params>
                <param><value><string>https://source.example/post</string></value></param>
                <param><value>https://target.example/article</value></param>
              </params>
            </methodCall>
            XML;

        self::assertSame(
            [
                'methodName' => 'pingback.ping',
                'params' => ['https://source.example/post', 'https://target.example/article'],
            ],
            $this->subject->decodeMethodCall($xml),
        );
    }

    #[Test]
    public function decodesScalarArrayAndStructValues(): void
    {
        $xml = <<<'XML'
            <methodCall>
              <methodName>test</methodName>
              <params>
                <param><value><i4>42</i4></value></param>
                <param><value><boolean>1</boolean></value></param>
                <param><value><array><data><value><int>1</int></value><value>two</value></data></array></value></param>
                <param><value><struct><member><name>key</name><value><double>1.5</double></value></member></struct></value></param>
              </params>
            </methodCall>
            XML;

        self::assertSame(
            [42, true, [1, 'two'], ['key' => 1.5]],
            $this->subject->decodeMethodCall($xml)['params'],
        );
    }

    #[Test]
    public function rejectsDocumentsWithDoctype(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE methodCall [<!ENTITY x "y">]><methodCall><methodName>&x;</methodName></methodCall>';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1732300005);
        $this->subject->decodeMethodCall($xml);
    }

    #[Test]
    public function rejectsPayloadsThatAreNoMethodCall(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1732300001);
        $this->subject->decodeMethodCall('<methodResponse/>');
    }

    #[Test]
    public function rejectsMalformedXml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1732300004);
        $this->subject->decodeMethodCall('<methodCall>');
    }

    #[Test]
    public function encodesFaultAsSpecified(): void
    {
        $expected = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<methodResponse><fault><value><struct>'
            . '<member><name>faultCode</name><value><int>32</int></value></member>'
            . '<member><name>faultString</name><value><string>Target &lt;unknown&gt;</string></value></member>'
            . '</struct></value></fault></methodResponse>';

        self::assertSame($expected, $this->subject->encodeFault(32, 'Target <unknown>'));
    }

    #[Test]
    public function encodedResponseCanBeParsedAgain(): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($this->subject->encodeResponse('Ping registered')));
        self::assertSame(
            'Ping registered',
            $document->getElementsByTagName('string')->item(0)?->textContent,
        );
    }
}
