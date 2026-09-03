<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\Service;

use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\Db\DocumensoFile;
use OCA\Documenso\Db\DocumensoFileMapper;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class FileService {
	public function __construct(
		private DocumensoAPIService $apiService,
		private DocumensoFileMapper $fileMapper,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
		private INotificationManager $notificationManager,
	) {
	}

	/**
	 * Check a tracked Documenso document and update the Nextcloud file when completed.
	 *
	 * @param array|null $document Optional document payload. When provided, the Documenso API is not queried for the document.
	 */
	public function processMapping(DocumensoFile $mapping, ?array $document = null): void {
		$userId = $mapping->getUserId();
		$documentId = $mapping->getDocumentId();
		$fileId = $mapping->getFileId();

		if ($document === null) {
			$document = $this->apiService->getDocument($userId, $documentId);
			if (isset($document['error'])) {
				$this->logger->warning(
					'Failed to fetch Documenso document ' . $documentId . ': ' . $document['error'],
					['app' => Application::APP_ID]
				);
				return;
			}
		}

		$status = isset($document['status']) && is_string($document['status'])
			? $document['status']
			: '';

		if ($status === 'REJECTED') {
			$this->logger->info(
				'Documenso document ' . $documentId . ' ended with status ' . $status,
				['app' => Application::APP_ID]
			);
			$this->fileMapper->delete($mapping);
			return;
		}

		if ($status !== 'COMPLETED') {
			return;
		}

		$download = $this->apiService->downloadSignedDocument($userId, $documentId);
		if (!isset($download['content'])) {
			$this->logger->warning(
				'Failed to download signed Documenso document ' . $documentId . ': ' . ($download['error'] ?? 'unknown error'),
				['app' => Application::APP_ID]
			);
			return;
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			$nodes = $userFolder->getById($fileId);
			if ($nodes === []) {
				throw new NotFoundException('File not found for id ' . $fileId);
			}
			$node = $nodes[0];
			if (!($node instanceof File)) {
				throw new NotFoundException('Node is not a file for id ' . $fileId);
			}
			$node->putContent($download['content']);

			// Create a notification for the user
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('document', (string)$documentId)
				->setSubject('document_signed', [
					'id' => $node->getId(),
					'name' => $node->getName(),
				]);
			$this->notificationManager->notify($notification);
		} catch (\Throwable $e) {
			$this->logger->error(
				'Failed to update Nextcloud file for Documenso document ' . $documentId . ': ' . $e->getMessage(),
				['app' => Application::APP_ID, 'exception' => $e]
			);
			$this->fileMapper->delete($mapping);
			return;
		}

		$this->fileMapper->delete($mapping);
		$this->logger->info(
			'Updated Nextcloud file ' . $fileId . ' from completed Documenso document ' . $documentId,
			['app' => Application::APP_ID]
		);
	}
}
