<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Documenso\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020300Date20260904124704 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('documenso_files')) {
			return null;
		}

		$table = $schema->getTable('documenso_files');
		if ($table->hasColumn('status')) {
			return null;
		}

		$table->addColumn('status', Types::STRING, [
			'notnull' => true,
			'length' => 32,
			'default' => 'DRAFT',
		]);

		$table->addUniqueIndex(['file_id'], 'documenso_files_file_id');
		return $schema;
	}
}
