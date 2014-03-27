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
	
}
	