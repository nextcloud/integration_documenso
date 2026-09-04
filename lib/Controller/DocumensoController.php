<?php

declare(strict_types=1);

namespace OCA\Documenso\Controller;

use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\BackgroundJob\CheckUserDocumentsJob;
use OCA\Documenso\Db\DocumensoFileMapper;
use OCA\Documenso\Service\DocumensoAPIService;
use OCA\Documenso\Service\FileService;
use OCA\Documenso\Service\UtilsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\BackgroundJob\IJobList;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DocumensoController extends Controller {

	public function __construct(
		string $AppName,
		IRequest $request,
		private IConfig $config,
		private DocumensoAPIService $documensoAPIService,
		private UtilsService $utilsService,
		private DocumensoFileMapper $documensoFileMapper,
		private FileService $fileService,
		private IJobList $jobList,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/info')]
	public function getDocumensoInfo(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'no user in context'], Http::STATUS_UNAUTHORIZED);
		}
		$token = $this->utilsService->getEncryptedUserValue($this->userId, 'token');
		$isConnected = ($token !== '');
		return new DataResponse([
			'connected' => $isConnected,
		]);
	}

	/**
	 * @param int $fileId
	 * @param string[] $targetEmails
	 * @param string[] $targetUserIds
	 * @param bool $overwriteOriginal
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/documenso/standalone-sign/{fileId}')]
	public function signStandalone(int $fileId, array $targetEmails = [], array $targetUserIds = [], bool $overwriteOriginal = false): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'no user in context'], Http::STATUS_UNAUTHORIZED);
		}
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$isConnected = ($token !== '' && $url !== '');
		if (!$isConnected) {
			return new DataResponse(['error' => 'Documenso connected account is not configured'], 401);
		}
		$file = $this->utilsService->getFile($fileId, $this->userId);
		if ($file === null) {
			return new DataResponse(['error' => 'You don\'t have access to this file'], 401);
		}
		if (!$overwriteOriginal) {
			try {
				$fileId = $this->fileService->copyFile($file);
			} catch (NotPermittedException $e) {
				return new DataResponse(['error' => $e->getMessage()], 401);
			}
		} else {
			// File only needs to be writeable if we're overwriting the original
			if (!$file->isUpdateable()) {
				return new DataResponse(['error' => 'You don\'t have permission to overwrite the original file'], 401);
			}
		}
		$signResult = $this->documensoAPIService->emailSignStandalone($fileId, $this->userId, $targetEmails, $targetUserIds);
		if (isset($signResult['error'])) {
			return new DataResponse($signResult, 401);
		}

		if (isset($signResult['documentId']) && is_numeric($signResult['documentId'])) {
			$documentId = (int)$signResult['documentId'];
			try {
				$this->documensoFileMapper->create($fileId, $documentId, $this->userId);
				$jobArgument = ['user_id' => $this->userId];
				$pollingDisabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'polling_disabled', '0') === '1';
				if (!$pollingDisabled && !$this->jobList->has(CheckUserDocumentsJob::class, $jobArgument)) {
					$this->jobList->add(CheckUserDocumentsJob::class, $jobArgument);
				}
			} catch (\Throwable $e) {
				$this->logger->error(
					'Failed to track Documenso document ' . $documentId . ': ' . $e->getMessage(),
					['app' => Application::APP_ID, 'exception' => $e]
				);
			}
		}

		return new DataResponse($signResult);
	}

	/**
	 * @param int $documentId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/documenso/distribute')]
	public function distribute(int $documentId): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'no user in context'], Http::STATUS_UNAUTHORIZED);
		}
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$isConnected = ($token !== '' && $url !== '');
		if (!$isConnected) {
			return new DataResponse(['error' => 'Documenso connected account is not configured'], Http::STATUS_UNAUTHORIZED);
		}
		$result = $this->documensoAPIService->distributeDocument($this->userId, $documentId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * Set config values
	 *
	 * @param array<string, string> $values
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/config')]
	public function setConfig(array $values): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'no user in context'], Http::STATUS_UNAUTHORIZED);
		}
		foreach ($values as $key => $value) {
			if ($key === 'polling_disabled') {
				$disabled = $value === '1';
				$this->config->setUserValue($this->userId, Application::APP_ID, $key, $disabled ? '1' : '0');
				$jobArgument = ['user_id' => $this->userId];
				if ($disabled) {
					$this->jobList->remove(CheckUserDocumentsJob::class, $jobArgument);
				} elseif ($this->documensoFileMapper->findAllByUserId($this->userId) !== []
					&& !$this->jobList->has(CheckUserDocumentsJob::class, $jobArgument)) {
					$this->jobList->add(CheckUserDocumentsJob::class, $jobArgument);
				}
				continue;
			}
			if ($key === 'token' && $value !== '') {
				$this->utilsService->setEncryptedUserValue($this->userId, $key, trim($value));
			} else {
				$this->config->setUserValue($this->userId, Application::APP_ID, $key, trim($value, " /\n\r\t\v\x00") . '/');
			}
		}

		return new DataResponse([]);
	}
}
