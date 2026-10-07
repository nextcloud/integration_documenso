<?php

declare(strict_types=1);

namespace OCA\Documenso\AppInfo;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\Documenso\Dashboard\DocumensoWidget;
use OCA\Documenso\Dav\DocumensoPlugin;
use OCA\Documenso\Listener\ContentSecurityPolicyListener;
use OCA\Documenso\Listener\NodeDeletedListener;
use OCA\Documenso\Notification\Notifier;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_documenso';
	public const DAV_PROPERTY_DOCUMENSO_STATE = '{http://nextcloud.org/ns}documenso-state';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $container->get(IEventDispatcher::class);
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
			Util::addInitScript(self::APP_ID, self::APP_ID . '-init');
			Util::addScript(self::APP_ID, self::APP_ID . '-filesplugin');
		});
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(DocumensoWidget::class);
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, ContentSecurityPolicyListener::class);
		$context->registerEventListener(NodeDeletedEvent::class, NodeDeletedListener::class);
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
		$eventDispatcher = $context->getServerContainer()->get(IEventDispatcher::class);
		$eventDispatcher->addListener(SabrePluginAddEvent::class, function (SabrePluginAddEvent $event) use ($context): void {
			$plugin = $context->getAppContainer()->get(DocumensoPlugin::class);
			$event->getServer()->addPlugin($plugin);
		});
	}
}
