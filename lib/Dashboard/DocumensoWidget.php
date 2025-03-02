<?php

namespace OCA\Documenso\Dashboard;

// use OCP\Dashboard\IWidget;
use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\Controller\DocumensoController;
use OCA\Documenso\Service\DocumensoAPIService;
use OCA\Documenso\Service\UtilsService;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;

class DocumensoWidget implements IButtonWidget, IIconWidget, IReloadableWidget {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private DocumensoController $controller,
		private DocumensoAPIService $documensoAPIService,
		private UtilsService $utilsService,
		private IConfig $config,
		private string $userId,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'integration_documenso';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Documenso');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'dashboard-documenso-icon';
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath('integration_documenso', 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		// No need to provide initial state or inject javascript code anymore
	}

	/**
	 * @inheritDoc
	 */
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		// TODO
		// $documents = $this->controller->getAllDocuments();
		// print json_decode($documents->getData()) ;
		$items = [];
		$emptyMessage = $this->l10n->t('No documents');
		$token = $this->utilsService->getEncryptedUserValue($this->userId, 'token');
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
		$isConnected = ($token !== '' && $url !== '');
		if (!$isConnected) {
			$emptyMessage = $this->l10n->t('Documenso is not connected');
		} else {

		}
		$response = $this->documensoAPIService->getDocumentList($this->userId);
		if (isset($response['error'])) {
			$emptyMessage = $this->l10n->t('Documenso service not available');
		} else {
			foreach ($response['documents'] as $document) {
				$documentUrl = $url . 'documents/' . $document['id'];
				$icon = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $document['title'], 'size' => 44, 'darkTheme' => true,]);
				$overlayIcon = $this->urlGenerator->imagePath('integration_documenso', 'dash_pending.svg');
				$status = $document['status'];
				if ($status === 'COMPLETED') {
					$subtitle = $this->l10n->t('Completed');
					$overlayIcon = $this->urlGenerator->imagePath('integration_documenso', 'dash_complete.svg');
				} elseif ($status === 'DRAFT') {
					$subtitle = $this->l10n->t('Draft');
					$overlayIcon = $this->urlGenerator->imagePath('integration_documenso', 'dash_draft.svg');
				} elseif ($status === 'PENDING') {
					$subtitle = $this->l10n->t('Waiting for signatures');
					$overlayIcon = $this->urlGenerator->imagePath('integration_documenso', 'dash_pending.svg');
				} else {
					$subtitle = $status;
					$overlayIcon = $this->urlGenerator->imagePath('integration_documenso', 'app_dark.svg');
				}

				$items[] = new WidgetItem($document['title'], $subtitle, $documentUrl, $icon, '', $overlayIcon);
			}
		}

		return new WidgetItems(
			$items,
			$emptyMessage,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getWidgetButtons(string $userId): array {
		return [
			new WidgetButton(
				WidgetButton::TYPE_MORE,
				$this->config->getUserValue($this->userId, Application::APP_ID, 'url'),
				$this->l10n->t('More documents'),
			),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getReloadInterval(): int {
		return 60;
	}
}
