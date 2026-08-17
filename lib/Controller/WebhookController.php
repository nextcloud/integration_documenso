<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\Controller;

use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\BackgroundJob\CheckUserDocumentsJob;
use OCA\Documenso\Db\DocumensoFileMapper;
use OCA\Documenso\Service\FileService;
use OCA\Documenso\Service\UtilsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class WebhookController extends Controller {

	public function __construct(
		string $AppName,
		IRequest $request,
		private UtilsService $utilsService,
		private DocumensoFileMapper $fileMapper,
		private FileService $fileService,
		private IConfig $config,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * Receive Documenso webhook events for a Nextcloud user.
	 *
	 * @param string $userId
	 * @param string|null $event
	 * @param array|null $payload
	 * @return DataResponse
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'documenso_webhook')]
	#[FrontpageRoute(verb: 'POST', url: '/webhook/{userId}')]
	public function handle(string $userId, ?string $event = null, ?array $payload = null): DataResponse {
		$receivedSecret = $this->request->getHeader('X-Documenso-Secret');
		$storedSecret = $this->utilsService->getEncryptedUserValue($userId, UtilsService::WEBHOOK_SECRET_KEY);
		if ($storedSecret === '' || $receivedSecret === '' || !hash_equals($storedSecret, $receivedSecret)) {
			$response = new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
			$response->throttle();
			return $response;
		}

		$this->config->setUserValue($userId, Application::APP_ID, 'polling_disabled', '1');
		$this->jobList->remove(CheckUserDocumentsJob::class, ['user_id' => $userId]);

		if ($event !== 'DOCUMENT_COMPLETED') {
			return new DataResponse(['received' => true]);
		}

		$documentId = 0;
		if (is_array($payload) && isset($payload['id']) && is_numeric($payload['id'])) {
			$documentId = (int)$payload['id'];
		}
		if ($documentId <= 0) {
			$this->logger->warning(
				'Documenso webhook DOCUMENT_COMPLETED is missing a document id',
				['app' => Application::APP_ID]
			);
			return new DataResponse(['received' => true]);
		}

		try {
			$mapping = $this->fileMapper->findByDocumentId($documentId, $userId);
		} catch (DoesNotExistException) {
			return new DataResponse(['received' => true]);
		} catch (\Throwable $e) {
			$this->logger->error(
				'Failed to load Documenso mapping for document ' . $documentId . ': ' . $e->getMessage(),
				['app' => Application::APP_ID, 'exception' => $e]
			);
			return new DataResponse(['error' => 'Failed to process webhook'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($mapping->getUserId() !== $userId) {
			return new DataResponse(['received' => true]);
		}

		$this->fileService->processMapping($mapping, $payload);
		return new DataResponse(['received' => true]);
	}
}
