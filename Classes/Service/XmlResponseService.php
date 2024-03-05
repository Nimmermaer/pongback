<?php

namespace Webicorns\Pongback\Service;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XmlResponseService
{
    public static function xmlErrorResponse(
        string $errorNumber,
        string $errorString,
        string $errorFile = '',
        string $errorLine = '',
        string $errorContext = ''
    ): ResponseInterface {
        $xml = xmlrpc_encode_request(null, [
            'faultCode' => $errorNumber,
            'faultString' => 'Remote XMLRPC Error from ' . $_SERVER['SERVER_NAME'] . sprintf(
                ': %s in %s:%s',
                $errorString,
                $errorFile,
                $errorLine
            ),
        ]);
        $responseFactory = GeneralUtility::makeInstance(ResponseFactory::class);
        $streamFactory = GeneralUtility::makeInstance(StreamFactory::class);
        return $responseFactory
            ->createResponse(404)
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withBody($streamFactory->createStream($xml));
    }

    public static function xmlResponse(string $xml): ResponseInterface
    {
        $responseFactory = GeneralUtility::makeInstance(ResponseFactory::class);
        $streamFactory = GeneralUtility::makeInstance(StreamFactory::class);
        return $responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withBody($streamFactory->createStream(($xml)));
    }
}
