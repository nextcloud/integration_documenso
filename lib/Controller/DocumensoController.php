<?php

declare(strict_types=1);

namespace OCA\Documenso\Controller;

use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\Service\DocumensoAPIService;
use OCA\Documenso\Service\UtilsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;

class DocumensoController extends Controller {

	public function __construct(
		string $AppName,
		IRequest $request,
		private IConfig $config,
		private IL10N $l,
		private IURLGenerator $urlGenerator,
		private DocumensoAPIService $documensoAPIService,
		private UtilsService $utilsService,
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
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/documenso/standalone-sign/{fileId}')]
	public function signStandalone(int $fileId, array $targetEmails = [], array $targetUserIds = []): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'no user in context'], Http::STATUS_UNAUTHORIZED);
		}
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$isConnected = ($token !== '' && $url !== '');
		if (!$isConnected) {
			return new DataResponse(['error' => 'Documenso connected account is not configured'], 401);
		}
		if (!$this->utilsService->userHasAccessTo($fileId, $this->userId)) {
			return new DataResponse(['error' => 'You don\'t have access to this file'], 401);
		}
		$signResult = $this->documensoAPIService->emailSignStandalone($fileId, $this->userId, $targetEmails, $targetUserIds);
		if (isset($signResult['error'])) {
			return new DataResponse($signResult, 401);
		} else {
			return new DataResponse($signResult);
		}
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
			if ($key === 'token' && $value !== '') {
				$this->utilsService->setEncryptedUserValue($this->userId, $key, trim($value));
			} else {
				$this->config->setUserValue($this->userId, Application::APP_ID, $key, trim($value, " /\n\r\t\v\x00") . '/');
			}
		}

		return new DataResponse([]);
	}
}
