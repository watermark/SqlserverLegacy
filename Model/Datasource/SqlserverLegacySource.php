<?php
App::uses('Sqlserver', 'Model/Datasource/Database');
App::uses('DatabaseException', 'SqlserverLegacy.Lib/Error');
App::uses('SqlsrvConnection', 'SqlserverLegacy.Lib/Model');

/**
 * $Id$
 */
class SqlserverLegacySource extends Sqlserver {
	
	public $description = "SQL Server Legacy DBO Driver";
	
	/**
	 * Connects to the database using options in the given configuration array.
	 *
	 * @return boolean True if the database could be connected, else false
	 * @throws MissingConnectionException
	 */
	public function connect() {
		$config = $this->config;
		$this->connected = false;

		//try to connect
		$this->_connection = new SqlsrvConnection($config);
		
		//check connection
		if( !$this->_connection->getLastGood()) {
			throw new MissingConnectionException(array(
				'class' => get_class($this),
				'message' => 'Connection Error'
			));
		}
		
		//mark as connected and setup
		$this->connected = true;
		if (!empty($config['settings'])) {
			foreach ($config['settings'] as $key => $value) {
				$this->_execute("SET $key $value");
			}
		}

		//return
		return $this->connected;
	}

	/**
	 * Disconnects from database.
	 *
	 * @return boolean Always true
	 */
	public function disconnect() {
		if ($this->_result instanceof SqlsvrStatement) {
			$this->_result->closeCursor();
		}
		if( $this->connected ) {
			mssql_close($this->_connection);
		}
		return parent::disconnect();
	}
	
	/**
	 * Check that MsSQLSRV is installed/loaded
	 *
	 * @return boolean
	 **/
	public function enabled() {
		return extension_loaded('mssql');
	}
	
	/**
	 * Gets the version string of the database server
	 *
	 * @return string The database version
	 */
	public function getVersion() {
		$stmt = $this->_connection->prepare("SELECT SERVERPROPERTY('productversion')");
		return $stmt->fetchColumn();
	}
	
	/**
	 * Checks if the result is valid
	 *
	 * @return boolean True if the result is valid else false
	 */
	public function hasResult() {
		return $this->_result instanceof SqlsrvStatement;
	}
	
	/**
	 * Returns a formatted error message from previous database operation.
	 *
	 * @param SqlsvrStatement $query the query to extract the error from if any
	 * @return string Error message with error number
	 */
	public function lastError(SqlsrvStatement $query = null) {
		if ($query) {
			$error = $query->errorInfo();
		} else {
			$error = $this->_connection->errorInfo();
		}
		if (empty($error[2])) {
			return null;
		}
		return $error[1] . ': ' . $error[2];
	}
	
	/**
	 * Returns the ID generated from the previous INSERT operation.
	 *
	 * @param mixed $source
	 * @return mixed
	 */
	public function lastInsertId($source = null) {
		$stmt = $this->_connection->prepare("SELECT SCOPE_IDENTITY()");
		return $stmt->fetchColumn();
	}
	
	/**
	 * Returns an array of the fields in given table name.
	 *
	 * @param Model|string $model Model object to describe, or a string table name.
	 * @return array Fields in table. Keys are name and type
	 * @throws CakeException
	 */
	public function describe($model) {
		$table = $this->fullTableName($model, false);
		$cache = parent::describe($table);
		if ($cache) {
			return $cache;
		}
		$fields = array();
		$table = $this->fullTableName($model, false);
		$cols = $this->_execute(
			"SELECT
				COLUMN_NAME as Field,
				DATA_TYPE as Type,
				COL_LENGTH('" . $table . "', COLUMN_NAME) as Length,
				IS_NULLABLE As [Null],
				COLUMN_DEFAULT as [Default],
				COLUMNPROPERTY(OBJECT_ID('" . $table . "'), COLUMN_NAME, 'IsIdentity') as [Key],
				NUMERIC_SCALE as Size
			FROM {$this->config['database']}.INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_NAME = '" . $table . "'"
		);
		if (!$cols) {
			throw new CakeException(__d('cake_dev', 'Could not describe table for %s', $table));
		}

		while ($column = $cols->fetch(PDO::FETCH_OBJ)) {
			$field = $column->Field;
			$fields[$field] = array(
				'type' => $this->column($column),
				'null' => ($column->Null === 'YES' ? true : false),
				'default' => $column->Default,
				'length' => $this->length($column),
				'key' => ($column->Key == '1') ? 'primary' : false
			);

			if ($fields[$field]['default'] === 'null') {
				$fields[$field]['default'] = null;
			}
			if ($fields[$field]['default'] !== null) {
				$fields[$field]['default'] = preg_replace(
					"/^[(]{1,2}'?([^')]*)?'?[)]{1,2}$/",
					"$1",
					$fields[$field]['default']
				);
				$this->value($fields[$field]['default'], $fields[$field]['type']);
			}

			if ($fields[$field]['key'] !== false && $fields[$field]['type'] === 'integer') {
				$fields[$field]['length'] = 11;
			} elseif ($fields[$field]['key'] === false) {
				unset($fields[$field]['key']);
			}
			if (in_array($fields[$field]['type'], array('date', 'time', 'datetime', 'timestamp'))) {
				$fields[$field]['length'] = null;
			}
			if ($fields[$field]['type'] === 'float' && !empty($column->Size)) {
				$fields[$field]['length'] = $fields[$field]['length'] . ',' . $column->Size;
			}
		}
		$this->_cacheDescription($table, $fields);
		$cols->closeCursor();
		return $fields;
	}
	
}
	