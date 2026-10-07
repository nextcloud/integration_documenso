<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getDocumentId()
 * @method void setDocumentId(int $documentId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 */
class DocumensoFile extends Entity {
	/** @var int */
	protected $fileId;
	/** @var int */
	protected $documentId;
	/** @var string */
	protected $userId;

	public function __construct() {
		$this->addType('fileId', 'integer');
		$this->addType('documentId', 'integer');
		$this->addType('userId', 'string');
	}
}
