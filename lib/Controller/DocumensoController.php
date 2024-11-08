<?php

declare(strict_types=1);

namespace OCA\Documenso\Controller;

use OCA\Documenso\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCA\Documenso\Service\DocumensoAPIService;
use OCA\Documenso\Service\UtilsService;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;

class DocumensoController extends Controller {
	private $userId;
	private $config;
	/**
	 * @var IL10N
	 */
	 private $l;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var DocumensoAPIService
	 */
	private $documensoAPIService;
	/**
	 * @var UtilsService
	 */
	private $utilsService;

	public function __construct($AppName,
		IRequest $request,
		IConfig $config,
		IL10N $l,
		IURLGenerator $urlGenerator,
		DocumensoAPIService $documensoAPIService,
		UtilsService $utilsService,
		?string $userId) {
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
		$this->documensoAPIService = $documensoAPIService;
		$this->utilsService = $utilsService;
		$this->userId = $userId;
	}

	/**
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/info')]
	public function getDocumensoInfo(): DataResponse {
		$token = $this->utilsService->getEncryptedUserValue($this->userId, 'token');
		$isConnected = ($token !== '');
		return new DataResponse([
			'connected' => $isConnected,
		]);
	}

	/**
	 * @param int $fileId
	 * @param array $targetEmails
	 * @param array $targetUserIds
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/documenso/standalone-sign/{fileId}')]
	public function signStandalone(int $fileId, array $targetEmails = [], array $targetUserIds = []): DataResponse {
		// $token = $this->utilsService->getEncryptedUserValue($this->userId, 'token');
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
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/config')]
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			if ($key === 'token' && $value !== '') {
				$this->utilsService->setEncryptedUserValue($this->userId, $key, trim($value));
			} else {
				$this->config->setUserValue($this->userId, Application::APP_ID, $key, trim($value));
			}
		}

		return new DataResponse([]);
	}
}
