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
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findByFileId(int $fileId): ?DocumensoFile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @param list<string> $statuses
	 * @return list<DocumensoFile>
	 * @throws Exception
	 */
	public function findByUserIdAndStatuses(string $userId, array $statuses): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter($statuses, IQueryBuilder::PARAM_STR_ARRAY)));

		/** @var list<DocumensoFile> */
		return $this->findEntities($qb);
	}

	/**
	 * @param list<int> $fileIds
	 * @return array<int, string> fileId => status
	 * @throws Exception
	 */
	public function findStatusesByFileIds(array $fileIds): array {
		if ($fileIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('file_id', 'status')
			->from($this->getTableName())
			->where($qb->expr()->in('file_id', $qb->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = [];
		foreach ($qb->executeQuery()->fetchAll() as $row) {
			$fileId = (int)$row['file_id'];
			if (!isset($result[$fileId]) && is_string($row['status'])) {
				$result[$fileId] = $row['status'];
			}
		}
		return $result;
	}

	/**
	 * @throws Exception
	 */
	public function create(int $fileId, int $documentId, string $userId): DocumensoFile {
		$entity = new DocumensoFile();
		$entity->setFileId($fileId);
		$entity->setDocumentId($documentId);
		$entity->setUserId($userId);
		$entity->setStatus(DocumensoFile::STATUS_DRAFT);
		return $this->insert($entity);
	}
}
