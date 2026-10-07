<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\Listener;

use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\Db\DocumensoFileMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<NodeDeletedEvent>
 */
class NodeDeletedListener implements IEventListener {

	public function __construct(
		private DocumensoFileMapper $fileMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!$event instanceof NodeDeletedEvent) {
			return;
		}

		try {
			$fileId = $event->getNode()->getId();
		} catch (\Throwable $e) {
			$this->logger->warning(
				'Failed to get node id on delete for Documenso cleanup: ' . $e->getMessage(),
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return;
		}

		try {
			$mapping = $this->fileMapper->findByFileId($fileId);
			if ($mapping !== null) {
				$this->fileMapper->delete($mapping);
			}
		} catch (\Throwable $e) {
			$this->logger->error(
				'Failed to clean up Documenso mappings for file ' . $fileId . ': ' . $e->getMessage(),
				['app' => Application::APP_ID, 'exception' => $e]
			);
		}
	}
}
