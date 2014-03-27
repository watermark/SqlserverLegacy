<?php
App::uses('SqlsrvStatement', 'SqlserverLegacy.Lib/Model');
App::uses('DatabaseException', 'SqlserverLegacy.Lib/Error');

/**
 * Provides a wrapper around SQL Server commands that tries
 * to emulate a PDO object
 */
class SqlsrvConnection extends Object {
	
	protected $_connection;
	protected $_lastgood;
	
	/**
	 * Create and connect to server
	 */
	public function __construct($config) {
		$this->_lastgood = false;
		
		if( $config['persistent'] ) {
			$this->_connection = mssql_pconnect(
				$config['host'],
				$config['login'],
				$config['password']
			);
		}
		else {
			$this->_connection = mssql_connect(
				$config['host'],
				$config['login'],
				$config['password']
			);
		}
		
		$this->_lastgood = ($this->_connection && mssql_select_db($config['database'], $this->_connection));
	}
	
	/**
	 * See if the last query was good n
	 */
	public function getLastGood() {
		return $this->_lastgood;
	}
	
	/**
	 * Tries to get the last error, false if there is none
	 */
	public function getErrorText() {
		if( $this->_lastgood === true ) return false;
		return mssql_get_last_message();
	}
	
	/**
	 * get the last error
	 */
	public function errorInfo() {
		if( $this->_lastgood === true ) return array(0, null, null);
		
		return array(
			0,
			0,
			mssql_get_last_message()
		);
	}
	
	/**
	 * Query
	 */
	public function exec($query) {
		//run the query
		$result = mssql_query($query, $this->_connection);
		if( $result === false ) {
			$this->_lastgood = false;
			throw new DatabaseException($this->getError());
		}
		
		//return the result object
		$this->_lastgood = true;
		if( $result === true ) return 0;
		return mssql_num_rows($result);
	}
	
	/**
	 * Try to make a result
	 */
	public function prepare($sql, $prepareOptions = array()) {
		return new SqlsrvStatement($this->_connection, $sql);
	}
	
	/**
	 * Begin
	 */
	public function beginTransaction() {
		$this->exec('BEGIN TRANSACTION');
		return $this->getLastGood();
	}
	
	/**
	 * Rollback
	 */
	public function rollBack() {
		$this->exec('ROLLBACK');
		return $this->getLastGood();
	}
	
	/**
	 * Rollback
	 */
	public function commit() {
		$this->exec('COMMIT');
		return $this->getLastGood();
	}
	
	/**
	 * Quote
	 */
	public function quote($stringToEscape) {
		return "'" . str_replace("'", "''", $stringToEscape) . "'";
	}
	
}
