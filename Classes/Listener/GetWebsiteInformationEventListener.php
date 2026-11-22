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

namespace Webicorns\Pongback\Listener;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
use Webicorns\Pongback\Service\XmlResponseService;

class GetWebsiteInformationEventListener
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function __invoke(BeforePingbackCreateEvent $event): void
    {
        $pingback = $event->getPingback();
        $targetLink = $pingback->getTargetLink();
        $sourceLink = $pingback->getSourceLink();

        $pseudoTag = '#%$PINGBACK-' . time() . '$%#';
        $client = new Client([
            RequestOptions::TIMEOUT => 10,
        ]);

        try {
            $response = $client->request('GET', $sourceLink);
            $sourceContent = (string) $response->getBody();
            $pattern = '@<a\s+[^>]*href\s*=\s*"' . preg_quote($targetLink, '@') . '"[^>]*>.*?</a>@s';
            preg_match($pattern, $sourceContent, $matches);
            $originalLink = $matches[0] ?? '';

            if ($originalLink !== '') {
                $sourceContent = str_replace($originalLink, $pseudoTag, $sourceContent);
            }

            $tagLessData = (string) preg_replace('#\s+#', ' ', $sourceContent);
            $pos = strpos($tagLessData, $pseudoTag);

            if ($pos !== false) {
                $informationString = '...' . trim(substr($tagLessData, $pos - 100, 200)) . '...';
                $informationString = strip_tags($informationString, '<a>');
                $pingback->setSerializedInformation(str_replace($pseudoTag, $originalLink, $informationString));
            } else {
                throw new \Exception('No Information found in source.', 1732300011);
            }
        } catch (RequestException) {
            $pingback->addValidationError(XmlResponseService::xmlErrorResponse(16, 'Source Link Unreachable'));
        } catch (\Exception $e) {
            $pingback->addValidationError(XmlResponseService::xmlErrorResponse(17, $e->getMessage()));
        }

        $pingback->setPid($this->findPidByUri($targetLink));
        $event->setPingback($pingback);
    }

    private function findPidByUri(string $targetLink): int
    {
        $sites = $this->siteFinder->getAllSites();
        $target = parse_url($targetLink);
        $connection = $this->connectionPool->getConnectionForTable('pages');
        $page = 0;
        foreach ($sites as $site) {
            if ($site->getBase()->getHost() === $target['host']) {
                $result = $connection->select(['*'], 'pages', [
                    'slug' => $target['path'],
                ], )->fetchAssociative();
                $page = $result['uid'] ?? 0;
                break;
            }
        }

        return $page;
    }
}
