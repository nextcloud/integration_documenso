<?php

declare(strict_types=1);

namespace OCA\Documenso\AppInfo;

use OCA\Documenso\Dashboard\DocumensoWidget;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Documenso\Listener\ContentSecurityPolicyListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_documenso';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $container->get(IEventDispatcher::class);
		// load files plugin script
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
			Util::addscript(self::APP_ID, self::APP_ID . '-filesplugin');
		});
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(DocumensoWidget::class);
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, ContentSecurityPolicyListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
