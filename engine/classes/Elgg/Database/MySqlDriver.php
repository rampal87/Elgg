<?php

/**
 * WARNING: API IN FLUX. DO NOT USE DIRECTLY.
 *
 * @access private
 * 
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
class Elgg_Database_MySqlDriver implements Elgg_Database_Driver {

	/**
	 * @var Elgg_Database_MySqlConnection
	 */
	protected $connection = null;

	/**
	 * Constructor
	 *
	 * @param array $connectionParameters
	 */
	public function __construct($connectionParameters) {
		$connection = new Elgg_Database_MySqlConnection($connectionParameters);
		$this->registerConnection($connection);
	}

	/**
	 * Register connection
	 *
	 * @param Elgg_Database_MySqlConnection $connection
	 * @return Elgg_Database_MySqlDriver
	 */
	public function registerConnection(Elgg_Database_MySqlConnection $connection) {
		$this->connection = $connection;
		$this->connection->setDriver($this);
		return $this;
	}

	/**
	 * Check environment
	 *
	 * @return void
	 */
	public function checkEnvironment() {
		if (!extension_loaded('mysql')) {
			throw new DatabaseException('The mysql extension is required for this adapter but the extension is not loaded');
		}
	}

	/**
	 * Get connection
	 *
	 * @return Connection
	 */
	public function getConnection() {
		return $this->connection;
	}

	/**
	 * Create result
	 *
	 * @param resource|bool $resource
	 * @return Elgg_Database_MySqlResult
	 */
	public function createResult($resource) {
		$result = new Elgg_Database_MySqlResult();
		$result->initialize($resource, $this->connection->getLastGeneratedValue());
		return $result;
	}

	/**
	 * Get last generated value
	 *
	 * @return int
	 */
	public function getLastGeneratedValue() {
		return $this->connection->getLastGeneratedValue();
	}

}
