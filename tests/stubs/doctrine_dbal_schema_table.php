<?php

namespace Doctrine\DBAL\Schema {
	class Table {
		/**
		 * @param mixed[] $options
		 * @return self
		 */
		public function addColumn(string $name, string $typeName, array $options = []) {
			return $this;
		}

		/**
		 * @param string[] $columnNames
		 * @return self
		 */
		public function setPrimaryKey(array $columnNames, $indexName = false) {
			return $this;
		}

		/**
		 * @param string[] $columnNames
		 * @param mixed[] $options
		 * @return self
		 */
		public function addUniqueIndex(array $columnNames, $indexName = null, array $options = []) {
			return $this;
		}
	}
}
