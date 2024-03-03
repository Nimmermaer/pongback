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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;

class GetWebsiteInformationEventListener
{
    public function __invoke(BeforePingbackCreateEvent $event): void
    {
        $pingback = $event->getPingback();
        $targetLink = $pingback->targetLink;
        $sourceLink = $pingback->sourceLink;

        $pseudoTag = '#%$PINGBACK-' . time() . '$%#';
        $client = new Client([
            RequestOptions::VERIFY => false,
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

            file_put_contents('sample.php', $sourceContent);

            $tagLessData = file_get_contents('sample.php');

            $tagLessData = preg_replace('#\s+#', ' ', $tagLessData);
            $pos = strpos($tagLessData, $pseudoTag);

            if ($pos !== false) {
                $informationString = '...' . trim(substr($tagLessData, $pos - 100, 200)) . '...';
                $informationString = strip_tags($informationString, '<a>');
                $pingback->serializedInformation = str_replace($pseudoTag, $originalLink, $informationString);
            } else {
                throw new \Exception('No Information found in source.');
            }
        } catch (RequestException) {
            $error = GeneralUtility::makeInstance(PingbackController::class);
            $pingback->validationError[] = $error->xmlErrorResponse('500', 'Source Link Unreachable');
        } catch (\Exception $e) {
            $error = GeneralUtility::makeInstance(PingbackController::class);
            $pingback->validationError[] = $error->xmlErrorResponse('500', $e->getMessage());
        }

        $pingback->setPid($this->findPidByUri($targetLink));
        $event->setPingback($pingback);
    }

    private function findPidByUri(string $targetLink): int
    {
        $sites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
        $target = parse_url($targetLink);
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $page = 0;
        foreach ($sites as $site) {
            if ($site->getBase()->getHost() === $target['host']) {
                $result = $connection->select(['*'], 'pages', [
                    'slug' => $target['path'],
                ],)->fetchAssociative();
                $page = $result['uid'] ?? 0;
                break;
            }
        }

        return $page;
    }
}
