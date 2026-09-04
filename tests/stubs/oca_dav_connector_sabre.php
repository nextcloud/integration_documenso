<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\DAV\Connector\Sabre {
	use Sabre\DAV\ICollection;
	use Sabre\DAV\INode;

	abstract class Node implements INode {
		public function getId(): int {
			return 0;
		}

		public function getPath(): string {
			return '';
		}
	}

	class Directory extends Node implements ICollection {
		/**
		 * @return list<Node>
		 */
		public function getChildren(): array {
			return [];
		}
	}
}
