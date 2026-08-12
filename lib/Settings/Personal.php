<?php

namespace OCA\Documenso\Settings;

use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\Service\UtilsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ICrypto $crypto,
		private IURLGenerator $urlGenerator,
		private UtilsService $utilsService,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {

		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$token = $token === '' ? '' : $this->crypto->decrypt($token);
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$pollingDisabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'polling_disabled', '0') === '1';

		$webhookSecret = '';
		$webhookUrl = '';
		if ($this->userId !== null) {
			$webhookSecret = $this->utilsService->getOrCreateWebhookSecret($this->userId);
			$webhookUrl = $this->urlGenerator->linkToRouteAbsolute(
				Application::APP_ID . '.webhook.handle',
				['userId' => $this->userId],
			);
		}

		$userConfig = [
			// don't expose the token to the user
			'token' => $token === '' ? '' : 'dummyToken',
			'url' => $url,
			'user_name' => $userName,
			'webhook_url' => $webhookUrl,
			'webhook_secret' => $webhookSecret,
			'polling_disabled' => $pollingDisabled,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
