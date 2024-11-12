<?php

namespace OCA\Documenso\Dashboard;

// use OCA\Documenso\AppInfo\Application;
// use OCP\Dashboard\IWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetItems;
use OCP\Dashboard\Model\WidgetButton;
use OCP\IL10N;
use OCP\IURLGenerator;

use OCP\Util;

class DocumensoWidget implements IButtonWidget, IIconWidget, IReloadableWidget {
    public function __construct(
        private IL10N $l10n,
        private IURLGenerator $urlGenerator,
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
        return $this->urlGenerator->linkToRouteAbsolute('integration_documenso.view.index');
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
        $items = [/* fancy items */];
        return new WidgetItems(
            $items,
            empty($items) ? $this->l10n->t('No items') : '',
        );
    }

    /**
     * @inheritDoc
     */
    public function getWidgetButtons(string $userId): array {
        return [
            new WidgetButton(
                WidgetButton::TYPE_MORE,
                $this->urlGenerator->linkToRouteAbsolute('integration_documenso.view.index'),
                $this->l10n->t('More items'),
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