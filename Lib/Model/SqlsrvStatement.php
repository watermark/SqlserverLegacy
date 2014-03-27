<?php

/**
 * $Id$
 */
class SqlsrvStatement extends Object {
	
	public $sql;
	
	protected $_defaultFetchMode;
	protected $_boundValues;
	protected $_result;
	protected $_link;
	
	/**
	 * Create
	 */
	public function __construct($link, $sql) {
		$this->sql = $sql;
		$this->_defaultFetchMode = PDO::FETCH_BOTH;
		$this->_boundValues = array();
		$this->_link = $link;
	}
	
	/**
	 * cleanup
	 */
	public function __destruct() {
		$this->closeCursor();
	}
	
	/**
	 * Frees the result
	 */
	public function closeCursor() {
		if( !$this->_result ) return true;
		$result = mssql_free_result($this->_result);
		$this->_result = null;
		$this->_boundValues = array();
		return $result;
	}
	
	/**
	 * Store bound values
	 */
	public function bindValue($param, $value, $data_type = PDO::PARAM_STR ) {
		$this->_boundValues[$param] = $value;
	}
	
	/**
	 * Get the number of rows
	 */
	public function rowCount() {
		if( !$this->_result ) return 0;
		return mssql_num_rows($this->_result);
	}
	
	/**
	 * get the number of columns
	 */
	public function columnCount() {
		if( !$this->_result ) return 0;
		return mssql_num_fields($this->_result);
	}
	
	/**
	 * Metadata of columns
	 */
	public function getColumnMeta($index) {
		if( !$this->_result ) return false;
		$type = mssql_field_length($this->_result, $index);
		
		return array(
			'name'=>mssql_field_name($this->_result, $index),
			'native_type'=>$type,
			'sqlsrv:decl_type'=>$type,
			'len'=>mssql_field_length($this->_result, $index)
		);
	}
	
	/**
	 * Runs the sql
	 */
	public function execute($params = null) {
		$query = $this->sql;
		$lastpos = 0;
		
		//see if there are any stored params
		if( !isset($params)) $params = $this->_boundValues;
		
		//argument replacement
		foreach($params as $arg) {
			if( ($lastpos = strpos($query, '?', $lastpos)) === false ) break;
			$query = substr_replace($query, $this->quote($arg), $lastpos, 1);
			$lastpos += strlen($arg);
		}

		//run and interpret query response
		$this->_result = mssql_query($query, $this->_link);
		if( $this->_result === false ) {
			$this->_result = null;
			return false;
		}
		
		if( $this->_result === true ) $this->_result = null;
		return true;
	}
	
	/**
	 * Set the default fetch mode for this statement
	 */
	public function setFetchMode($mode) {
		$this->_defaultFetchMode = $mode;
	}
	
	/**
	 * Get an individual column
	 */
	public function fetchColumn($column_number = 0) {
		$row = $this->fetch(PDO::FETCH_NUM);
		if( empty($row) || empty($row[$column_number])) return false;
		return $row[$column_number];
	}
	
	/**
	 * fetching a row
	 */
	public function fetch($type = null) {
		if( !$this->_result ) return array();
		if( !isset($type)) $type = $this->_defaultFetchMode;
		
		switch($type) {
			case PDO::FETCH_OBJ:
				return mssql_fetch_object($this->_result);
			case PDO::FETCH_LAZY:
			case PDO::FETCH_BOTH:
				return mssql_fetch_array($this->_result, MSSQL_BOTH);
			case PDO::FETCH_NUM:
				return mssql_fetch_row($this->_result);
		}
		
		throw new DatabaseException('Unimplemented fetch type');
		return false;
	}
	
	/**
	 * Get all of the records
	 */
	public function fetchAll($type = null) {
		$data = array();
		while($row = $this->fetch($type)) $data[] = $row;
		return $data;
	}
	
	/**
	 * Quote
	 */
	protected function quote($stringToEscape) {
		return "'" . str_replace("'", "''", $stringToEscape) . "'";
	}
	
	/**
	 * get the last error
	 */
	public function errorInfo() {
		return array(
			0,
			0,
			mssql_get_last_message()
		);
	}
	
}
