Intro
===============

CakePHP 2.x cannot connect to a MSSQL server on linux because the MSSQL PDO driver only
runs on Windows.

This plugin implements a minimal wrapper around the mssql_* functions to simulate MSSQL
PDO support on linux.

If you're running on Windows, you should probably use the PDO driver supplied by Microsoft
combined with the offical core Sqlserver datasource.  This datasource is meant to provide
MSSQL support on linux servers.  The mssql_* functions are not avaliable on Windows for
PHP 5.3+, therefore this datasource should not even work on Windows with PHP 5.3+.

Testing
================

The tests provided are the CakePHP core tests for Model/Datasource/Database/SqlserverTest
with minimal changes.

Tested on CakePHP 2.4.6, Ubuntu 12.04.4, PHP 5.3.10, connecting to SQL 2008 R2.

Example
================

in your database.php config,

public $default = array(
	'datasource' => 'SqlserverLegacy.SqlserverLegacySource',
	'persistent' => false,
	'host'=>'hostname/ip to db server',
	'login' => 'username',
	'password' => 'password',
	'database' => 'database',
	'prefix' => '',
	'encoding' => 'utf8',
);

Use models as normal.

License
================

MIT. See LICENSE for details. Please contribute back if you can.