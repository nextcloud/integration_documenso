<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\BackgroundJob;

use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\Db\DocumensoFileMapper;
use OCA\Documenso\Service\FileService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * Per-user job: poll pending Documenso documents and update Nextcloud files when completed.
 */
class CheckUserDocumentsJob extends QueuedJob {
	private const RETRY_AFTER_SECONDS = 15 * 60;

	public function __construct(
		ITimeFactory $timeFactory,
		private FileService $fileService,
		private DocumensoFileMapper $fileMapper,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
	}

	/**
	 * @param array{user_id?: string} $argument
	 */
	#[\Override]
	protected function run($argument): void {
		$userId = isset($argument['user_id']) && is_string($argument['user_id']) ? $argument['user_id'] : '';
		if ($userId === '') {
			$this->logger->warning('CheckUserDocumentsJob missing user_id', ['app' => Application::APP_ID]);
			return;
		}

		$mappings = $this->fileMapper->findAllByUserId($userId);
		if ($mappings === []) {
			return;
		}

		foreach ($mappings as $mapping) {
			$this->fileService->processMapping($mapping);
		}

		if ($this->fileMapper->findAllByUserId($userId) !== []) {
			$this->scheduleRetry($userId);
		}
	}

	private function scheduleRetry(string $userId): void {
		$argument = ['user_id' => $userId];
		if ($this->jobList->has(self::class, $argument)) {
			return;
		}
		$this->jobList->scheduleAfter(
			self::class,
			$this->time->getTime() + self::RETRY_AFTER_SECONDS,
			$argument,
		);
	}
}
