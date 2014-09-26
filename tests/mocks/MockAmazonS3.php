<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2014, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_aws\tests\mocks;

class MockAmazonS3 {

	public $construct = null;

	public $calls = [];

	public static $callsStatic = [];

	public function __construct() {
		$this->construct = func_get_args();
	}

	public function __call($method, $params = array()) {
		return $this->calls[] = compact('method', 'params');
	}

	public static function __callStatic($method, $params) {
		return static::$callsStatic[] = compact('method', 'params');
	}

	public function if_bucket_exists($bucket) {
		$method = __FUNCTION__;
		$params = func_get_args();
		$this->calls[] = compact('method', 'params');
		return $bucket === 'li3_aws';
	}

	public function if_object_exists($bucket, $filename) {
		$method = __FUNCTION__;
		$params = func_get_args();
		$this->calls[] = compact('method', 'params');
		return $bucket === 'li3_aws' && $filename === 'test_existing_file';
	}
}

?>