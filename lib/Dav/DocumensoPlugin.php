<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\Dav;

use OCA\DAV\Connector\Sabre\Directory as SabreDirectory;
use OCA\DAV\Connector\Sabre\Node as SabreNode;
use OCA\Documenso\AppInfo\Application;
use OCA\Documenso\Db\DocumensoFileMapper;
use Sabre\DAV\ICollection;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class DocumensoPlugin extends ServerPlugin {
	/** @var array<int, string|null> */
	private array $cachedStatuses = [];
	/** @var array<string, true> */
	private array $cachedDirectories = [];

	public function __construct(
		private DocumensoFileMapper $fileMapper,
	) {
	}

	public function initialize(Server $server): void {
		$server->on('propFind', [$this, 'propFind']);
		$server->on('preloadCollection', $this->preloadCollection(...));
	}

	/**
	 * @param PropFind $propFind
	 * @param INode $node
	 */
	public function propFind(PropFind $propFind, INode $node) {
		if (!$node instanceof SabreNode) {
			return;
		}
		$nodeId = $node->getId();
		$propFind->handle(
			Application::DAV_PROPERTY_DOCUMENSO_STATE,
			function () use ($nodeId) {
				return $this->getStatus($nodeId);
			}
		);
	}

	public function getPluginName(): string {
		return Application::APP_ID;
	}

	/**
	 * @return array{name: string, description: string}
	 */
	public function getPluginInfo(): array {
		return [
			'name' => $this->getPluginName(),
			'description' => 'Provides Documenso signing state in PROPFIND WebDAV requests',
		];
	}

	/**
	 * @param PropFind $propFind
	 * @param ICollection $collection
	 * @return void
	 */
	public function preloadCollection(PropFind $propFind, ICollection $collection): void {
		if (!($collection instanceof SabreNode)) {
			return;
		}
		if ($collection instanceof SabreDirectory
			&& !isset($this->cachedDirectories[$collection->getPath()])
			&& (!is_null($propFind->getStatus(Application::DAV_PROPERTY_DOCUMENSO_STATE)))
		) {
			$folderContent = $collection->getChildren();
			$fileIds = [$collection->getId()];
			foreach ($folderContent as $info) {
				$fileIds[] = $info->getId();
			}
			$this->preloadStatuses($fileIds);
			$this->cachedDirectories[$collection->getPath()] = true;
		}
	}

	/**
	 * @param list<int> $fileIds
	 */
	private function preloadStatuses(array $fileIds): void {
		$missing = [];
		foreach ($fileIds as $fileId) {
			if (!array_key_exists($fileId, $this->cachedStatuses)) {
				$missing[] = $fileId;
			}
		}
		if ($missing === []) {
			return;
		}
		$statuses = $this->fileMapper->findStatusesByFileIds($missing);
		foreach ($missing as $fileId) {
			$this->cachedStatuses[$fileId] = $statuses[$fileId] ?? null;
		}
	}

	private function getStatus(int $fileId): ?string {
		$this->preloadStatuses([$fileId]);
		return $this->cachedStatuses[$fileId];
	}
}
