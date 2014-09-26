<?php
namespace li3_aws\extensions\adapter\storage\filesystem;

use AmazonS3;
use Exception;

/**
 * An Simple Storage Service (S3) filesystem adapter implementation.
 *
 * The S3 adapter is meant to be used through the `FileSystem` interface, which abstracts away
 * bucket creation, adapter instantiation, and filter implemenation.
 *
 * A simple configuration of this adapter can be accomplished in `config/bootstrap/filesystem.php`
 * as follows:
 *
 * {{{
 * FileSystem::config([
 *     'cloud' => '['adapter' => 'S3'],
 * ]);
 * }}}
 *
 */
class S3 extends \lithium\core\Object {

	/**
	 * Stores the Amazon S3 instance.
	 *
	 * @var object
	 */
	public $_s3 = null;

	/**
	 * Auto config array
	 *
	 * @var array
	 */
	protected $_autoConfig = ['s3'];

	/**
	 * Class constructor.
	 *
	 * @see li3_filesystem\storage\FileSystem::config()
	 * @param array $config The options are:
	 *                      - `'protocol'`  : Default protocol to use to use for urls generation.
	 *                      - `'bucket'`    : The S3 bucket name.
	 *                      - `'key'`       : The S3 key.
	 *                      - `'secret'`    : The S3 secret key.
	 *                      - `'region'`    : The S3 region name.
	 *                      - `'timeout'`   : Default timeout for signed urls.
	 *                      - `'cloudfront'`: The cloudfront proxy domain name (optionnal).
	 *                      - `'cdn'`       : If `true` use the cloudfront proxy domain name
	 *                        instead of S3 for default URL generation. Defaults to `false`.
	 *                      - `'s3'`        : The S3 instance (optionnal).
	 */
	public function __construct($config = []) {
		$defaults = [
			'protocol'   => 'https',
			'bucket'     => null,
			'key'        => null,
			'secret'     => null,
			'region'     => AmazonS3::REGION_US_E1,
			'timeout'    => '+15 minutes',
			'cloudfront' => null,
			'cdn'        => false,
			's3'         => null
		];
		parent::__construct($config + $defaults);

		unset($this->_config['s3']);

		if (!$this->_s3) {
			$this->_s3 = new AmazonS3($this->_config);
		}
	}

	/**
	 * Upload a file to S3
	 *
	 * @param  string $filename    The file path in the bucket.
	 * @param  string $data    The data to upload.
	 * @param  array  $options Possible options are:
	 *                         - `'fileUpload'` : the URL/path for the file to upload. If set, it'll be used instead
	 *                           of `$data`. Defaults: `null`.
	 *                         - `'autoBucket'` : allow to auto create the bucket. Defaults: `true`.
	 *                         - `'overwrite'`  : allow to auto overwrite a file. Defaults: `true`.
	 *                         - `'acl'`        : the ACL privilege for this file. Defaults: `AmazonS3::ACL_PUBLIC`.
	 *                           Possible privileges are:
	 *                           `AmazonS3::ACL_PRIVATE`            (ACL: Owner-only read/write).
	 *                           `AmazonS3::ACL_PUBLIC`             (ACL: Owner read/write, public read).
	 *                           `AmazonS3::ACL_OPEN`               (ACL: Public read/write).
	 *                           `AmazonS3::ACL_AUTH_READ`          (ACL: Owner read/write, authenticated read).
	 *                           `AmazonS3::ACL_OWNER_READ`         (ACL: Bucket owner read).
	 *                           `AmazonS3::ACL_OWNER_FULL_CONTROL` (ACL: Bucket owner read).
	 *                          For all other options see `AmazonS3::create_object()`.
	 * @return object
	 */
	public function write($filename, $data = '', $options = []) {
		$defaults = [
			'fileUpload' => null,
			'autoBucket' => true,
			'overwrite'  => true,
			'acl'        => AmazonS3::ACL_PUBLIC
		];
		$options += $defaults;

		$config = $this->_config;

		return function($self, $params) use ($config, $options) {
			$body = $params['data'];
			$filename = $params['filename'];

			$bucket = $config['bucket'];
			$region = $config['region'];

			$autoBucket = $options['autoBucket'];
			unset($options['autoBucket']);

			$overwrite = $options['overwrite'];
			unset($options['overwrite']);

			if (!$self->s3()->if_bucket_exists($bucket)) {
				if ($autoBucket) {
					$self->s3()->create_bucket($bucket, $region);
				} else {
					throw new Exception("Unexisting S3 bucket `{$bucket}`.");
				}
			}

			if ($self->s3()->if_object_exists($bucket, $filename) && !$overwrite) {
				throw new Exception("File `{$filename}` already exists in S3 bucket `{$bucket}`.");
			}

			return $self->s3()->create_object($bucket, $filename, ['body' => $body] + $options);
		};
	}

	/**
	 * Download a file from S3
	 *
	 * @param  string $filename    The file path in the bucket.
	 * @param  array  $options See `AmazonS3::get_object()` for options.
	 * @return object
	 */
	public function read($filename, $options = []) {
		$config = $this->_config;

		return function($self, $params) use ($config, $options) {
			$filename = $params['filename'];
			return $self->s3()->get_object($config['bucket'], $filename, $options);
		};
	}

	/**
	 * Delete a file in S3
	 *
	 * @param  string $filename    The file path in the bucket to delete.
	 * @param  array  $options See `AmazonS3::delete_object()` for options.
	 * @return object
	 */
	public function delete($filename, $options = []) {
		$config = $this->_config;

		return function($self, $params) use ($config, $options) {
			$filename = $params['filename'];
			return $self->s3()->delete_object($config['bucket'], $filename, $options);
		};
	}

	/**
	 * Get a S3 url.
	 *
	 * @param  string $filename    The file path in the bucket to get the url.
	 * @param  array  $options The options are:
	 *                         - `'protocol'`  : Protocol to use to use for the url.
	 *                         - `'cloudfront'`: The cloudfront proxy domain name (optionnal). See config for default.
	 *                         - `'cdn'`       : If `true` use the cloudfront proxy domain name. See config for default.
	 * @return string
	 */
	public function url($path, $options = []) {
		$options += $this->_config;
		$protocol = $options['protocol'] ? $options['protocol'] . ':' : '';
		if ($options['cdn']) {
			if (!$options['cloudfront']) {
				throw new Exception("CDN need a valid domain address for `'cloudfront'` option.");
			}
			$domain = $options['cloudfront'];
		} else {
			$domain = "{$options['bucket']}.s3.amazonaws.com";
		}
		return "{$protocol}//{$domain}/{$path}";
	}

	/**
	 * Get a S3 signed url.
	 *
	 * @param  string $path    The file path in the bucket to get the url.
	 * @param  array  $options The options are:
	 *                         - `'protocol'`  : Protocol to use to use for the signed url.
	 *                         - `'timeout'`   : Timeout for signed urls. See config for default.
	 *                         - `'cloudfront'`: The cloudfront proxy domain name (optionnal). See config for default.
	 *                         - `'cdn'`       : If `true` use the cloudfront proxy domain name. See config for default.
	 * @return string
	 */
	public function signUrl($path, $options = []) {
		$options += $this->_config + [
			'signatureOnly' => false
		];
		$expires = is_string($options['timeout']) ? strtotime($options['timeout']) : time() + $options['timeout'];

		$string_to_sign = "GET\n\n\n{$expires}\n/" . $options['bucket'] . "/{$path}";
		$signature = base64_encode((hash_hmac("sha1", $string_to_sign, $options['secret'], true)));

		$qs = http_build_query([
			'AWSAccessKeyId' => $options['key'],
			'Expires' => $expires,
			'Signature' => $signature,
		]);
		return $options['signatureOnly'] ? $qs : $this->url($path) . "?{$qs}";
	}

	/**
	 * Return the Amazon S3 instance
	 *
	 * @return object
	 */
	public function s3() {
		return $this->_s3;
	}
}
