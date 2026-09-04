<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<DocumensoFile>
 */
class DocumensoFileMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'documenso_files');
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByDocumentId(int $documentId, string $userId): DocumensoFile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('document_id', $qb->createNamedParameter($documentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntity($qb);
	}

	/**
	 * @return list<DocumensoFile>
	 * @throws Exception
	 */
	public function findAllByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		/** @var list<DocumensoFile> */
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function create(int $fileId, int $documentId, string $userId): DocumensoFile {
		$entity = new DocumensoFile();
		$entity->setFileId($fileId);
		$entity->setDocumentId($documentId);
		$entity->setUserId($userId);
		return $this->insert($entity);
	}
}
