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
 * @method string getStatus()
 * @method void setStatus(string $status)
 */
class DocumensoFile extends Entity {
	public const STATUS_DRAFT = 'DRAFT';
	public const STATUS_PENDING = 'PENDING';
	public const STATUS_COMPLETED = 'COMPLETED';
	public const STATUS_REJECTED = 'REJECTED';
	public const STATUS_CANCELLED = 'CANCELLED';

	public const STATUSES = [
		self::STATUS_DRAFT,
		self::STATUS_PENDING,
		self::STATUS_COMPLETED,
		self::STATUS_REJECTED,
		self::STATUS_CANCELLED,
	];

	/** @var int */
	protected $fileId;
	/** @var int */
	protected $documentId;
	/** @var string */
	protected $userId;
	/** @var string */
	protected $status;

	public function __construct() {
		$this->addType('fileId', 'integer');
		$this->addType('documentId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('status', 'string');
	}
}
