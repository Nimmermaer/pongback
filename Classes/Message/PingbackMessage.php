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

namespace Webicorns\Pongback\Message;

use TYPO3\CMS\Core\Attribute\WebhookMessage;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Messaging\WebhookMessageInterface;

#[WebhookMessage(
    identifier: 'pongback/pingback/page-modification',
    description: 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:sys_webhook.webhook_type.pongback-pingback-page-modification'
)]
final readonly class PingbackMessage implements WebhookMessageInterface
{
    public function __construct(
        private string $action,
        private int $uid,
        private array $record,
        private string $table,
        private string $url,
        private string $siteIdentifier,
        private ?BackendUserAuthentication $author = null,
        private ?array $modifiedFields = null
    ) {
    }
    public function jsonSerialize(): array
    {
        $data = [
            'action' => $this->action,
            'identifier' => $this->uid,
            'record' => $this->record,
            'table' => $this->table,
            'url' => $this->url,
            'site' => $this->siteIdentifier,
            'workspace' => $this->record['t3ver_wsid'] ?? 0,
        ];
        if ($this->author instanceof BackendUserAuthentication) {
            $data['author'] = [
                'uid' => $this->author->user['uid'],
                'username' => $this->author->user['username'],
                'isAdmin' => $this->author->isAdmin(),
            ];
        }

        if (is_array($this->modifiedFields)) {
            $data['changedFields'] = $this->modifiedFields;
        }

        return $data;
    }
}
