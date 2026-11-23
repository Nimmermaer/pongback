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

use PHPUnit\Framework\Attributes\DataProvider;
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

        $call = $this->subject->decodeMethodCall($xml);

        self::assertSame('pingback.ping', $call->methodName);
        self::assertSame(['https://source.example/post', 'https://target.example/article'], $call->params);
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
            $this->subject->decodeMethodCall($xml)->params,
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

    #[Test]
    public function trimsWhitespaceAroundMethodNameAndScalarValues(): void
    {
        $xml = '<methodCall><methodName> pingback.ping </methodName><params>'
            . '<param><value><int> 42 </int></value></param>'
            . '<param><value><i8>9007199254740993</i8></value></param>'
            . '<param><value><boolean> 1 </boolean></value></param>'
            . '<param><value><boolean>0</boolean></value></param>'
            . '<param><value><double> 1.5 </double></value></param>'
            . '<param><value><nil/></value></param>'
            . '<param/>'
            . '</params></methodCall>';

        $call = $this->subject->decodeMethodCall($xml);

        self::assertSame('pingback.ping', $call->methodName);
        self::assertSame([42, 9007199254740993, true, false, 1.5, null, null], $call->params);
    }

    #[Test]
    public function decodesStructsAndArraysDefensively(): void
    {
        $xml = '<methodCall><methodName>test</methodName><params>'
            . '<param><value><struct>'
            . '<member><name> first </name><value>1</value></member>'
            . '<member><name>second</name><value><int>2</int></value></member>'
            . '<member><value>no name</value></member>'
            . '<member><name>no value</name></member>'
            . '</struct></value></param>'
            . '<param><value><array></array></value></param>'
            . '<param><value><array><data><foo/><value>kept</value></data></array></value></param>'
            . '</params></methodCall>';

        self::assertSame(
            [['first' => '1', 'second' => 2], [], ['kept']],
            $this->subject->decodeMethodCall($xml)->params,
        );
    }

    #[Test]
    public function rejectsWhitespaceOnlyPayloads(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1732300003);
        $this->subject->decodeMethodCall("  \n ");
    }

    #[Test]
    public function rejectsMethodCallsWithoutMethodName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1732300002);
        $this->subject->decodeMethodCall('<methodCall><methodName>  </methodName></methodCall>');
    }

    #[Test]
    public function restoresTheLibxmlErrorHandlingState(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            try {
                $this->subject->decodeMethodCall('<methodCall><broken>');
            } catch (\InvalidArgumentException) {
            }

            self::assertFalse(libxml_use_internal_errors(true), 'internal error handling must be reset');
            self::assertSame([], libxml_get_errors(), 'libxml errors must be cleared');
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    #[Test]
    public function escapesSpecialCharactersInStringsAndMemberNames(): void
    {
        self::assertStringContainsString(
            '<string>&lt;a href=&quot;x&quot;&gt;Tom &amp; Jerry&apos;s&lt;/a&gt;</string>',
            $this->subject->encodeResponse('<a href="x">Tom & Jerry\'s</a>'),
        );
    }

    #[Test]
    public function encodedMethodCallsCanBeDecodedAgain(): void
    {
        $xml = $this->subject->encodeMethodCall('pingback.ping', ['https://a.example/?x=1&y=<2>', 'https://b.example/']);

        $call = $this->subject->decodeMethodCall($xml);

        self::assertSame('pingback.ping', $call->methodName);
        self::assertSame(['https://a.example/?x=1&y=<2>', 'https://b.example/'], $call->params);
    }

    #[Test]
    public function decodesSuccessResponses(): void
    {
        $response = $this->subject->decodeMethodResponse($this->subject->encodeResponse('Thanks'));

        self::assertFalse($response->isFault());
        self::assertSame('Thanks', $response->value);
        self::assertNull($response->faultCode);
    }

    #[Test]
    public function decodesFaultResponses(): void
    {
        $response = $this->subject->decodeMethodResponse($this->subject->encodeFault(48, 'Already registered'));

        self::assertTrue($response->isFault());
        self::assertSame(48, $response->faultCode);
        self::assertSame('Already registered', $response->faultString);
        self::assertNull($response->value);
    }

    #[Test]
    public function decodesFaultsWithMissingMembersDefensively(): void
    {
        $response = $this->subject->decodeMethodResponse('<methodResponse><fault><value><struct></struct></value></fault></methodResponse>');

        self::assertTrue($response->isFault());
        self::assertSame(0, $response->faultCode);
        self::assertSame('', $response->faultString);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function invalidResponseProvider(): array
    {
        return [
            'method call instead of response' => ['<methodCall><methodName>x</methodName></methodCall>', 1732500001],
            'neither fault nor value' => ['<methodResponse><params></params></methodResponse>', 1732500002],
            'param without value' => ['<methodResponse><params><param/></params></methodResponse>', 1732500002],
        ];
    }

    #[Test]
    #[DataProvider('invalidResponseProvider')]
    public function rejectsInvalidResponses(string $xml, int $expectedCode): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode($expectedCode);
        $this->subject->decodeMethodResponse($xml);
    }
}
