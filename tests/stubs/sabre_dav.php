<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Sabre\DAV {
	interface INode {
	}

	interface ICollection extends INode {
	}

	class PropFind {
		/**
		 * @param string $key
		 * @param mixed $valueOrCallable
		 */
		public function handle(string $key, $valueOrCallable): void {
		}

		public function getStatus(string $propertyName): ?int {
			return null;
		}
	}

	class Server {
		public function on(string $eventName, callable $callable): void {
		}

		public function addPlugin(ServerPlugin $plugin): void {
		}
	}

	class ServerPlugin {
		public function initialize(Server $server): void {
		}

		public function getPluginName(): string {
			return '';
		}

		/**
		 * @return array{name: string, description: string}
		 */
		public function getPluginInfo(): array {
			return [
				'name' => '',
				'description' => '',
			];
		}
	}
}
