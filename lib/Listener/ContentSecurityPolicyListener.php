<?php

namespace OCA\Documenso\Listener;

use OCA\Documenso\AppInfo\Application;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * @implements IEventListener<AddContentSecurityPolicyEvent>
 */
class ContentSecurityPolicyListener implements IEventListener {

	public function __construct(
		private IConfig $config,
		private ?string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!$event instanceof AddContentSecurityPolicyEvent) {
			return;
		}
		if ($this->userId === null) {
			return;
		}

		$policy = new ContentSecurityPolicy();
		$host = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$policy->addAllowedFrameDomain($host);
		$event->addPolicy($policy);
	}
}
