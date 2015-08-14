<?php

namespace li3_aws\tests\cases\extensions\adapter\storage\filesystem;

use AmazonS3;
use li3_aws\extensions\adapter\storage\filesystem\S3;
use li3_aws\tests\mocks\MockAmazonS3;

class S3Test extends \lithium\test\Unit {

    protected $_adapter = null;

    public function setUp() {
        $this->_adapter = new S3([
            'protocol'   => 'http',
            'bucket'     => 'li3_aws',
            'key'        => 'BKIAJCQJZKAWSTNVBHUJ',
            'secret'     => 'TRDMBTZ1Ju1KUG4zKLbL1k8cJgh92UJQzrK4l1M',
            'region'     => AmazonS3::REGION_US_E1,
            'timeout'    => '+15 minutes',
            'cloudfront' => 'li3_aws.cloudfront.net',
            'cdn'        => false,
            's3'         => new MockAmazonS3()
        ]);
    }

    public function tearDown() {
    }

    public function testWrite() {
        $params = [
            'filename' => 'test_file',
            'data'     => 'test data'
        ];
        $closure = $this->_adapter->write($params['filename'], $params['data'], []);
        $closure($this->_adapter, $params);
        $s3 = $this->_adapter->s3();

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'if_bucket_exists',
            'params' => ['li3_aws']
        ], $call);

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'if_object_exists',
            'params' => ['li3_aws', 'test_file']
        ], $call);

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'create_object',
            'params' => [
                'li3_aws',
                'test_file',
                [
                    'body' => 'test data',
                    'fileUpload' => null,
                    'acl' => 'public-read',
                ]
            ]
        ], $call);
    }

    public function testWriteUsingFileInsteadOfStringBuffer() {
        $params = [
            'filename' => 'test_file',
            'data'     => null
        ];
        $closure = $this->_adapter->write($params['filename'], $params['data'], [
            'fileUpload' => '/path/to/the/file'
        ]);
        $closure($this->_adapter, $params);
        $s3 = $this->_adapter->s3();

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'if_bucket_exists',
            'params' => ['li3_aws']
        ], $call);

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'if_object_exists',
            'params' => ['li3_aws', 'test_file']
        ], $call);

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'create_object',
            'params' => [
                'li3_aws',
                'test_file',
                [
                    'body' => null,
                    'fileUpload' => '/path/to/the/file',
                    'acl' => 'public-read',
                ]
            ]
        ], $call);
    }

    public function testWriteDoesntOverwrite() {
        $params = [
            'filename' => 'test_existing_file',
            'data'     => 'test data'
        ];
        $closure = $this->_adapter->write($params['filename'], $params['data'], [
            'overwrite' => false
        ]);

        $fn = function() use ($params, $closure) {
            $closure($this->_adapter, $params);
        };

        $this->assertException('File `test_existing_file` already exists in S3 bucket `li3_aws`.', $fn);
    }

    public function testWriteDoesntAutoCreateBucket() {
        $this->_adapter = new S3([
            'protocol'   => 'https',
            'bucket'     => 'unexisting_bucket',
            'key'        => 'BKIAJCQJZKAWSTNVBHUJ',
            'secret'     => 'TRDMBTZ1Ju1KUG4zKLbL1k8cJgh92UJQzrK4l1M',
            'region'     => AmazonS3::REGION_US_E1,
            'timeout'    => '+15 minutes',
            'cloudfront' => null,
            'cdn'        => false,
            's3'         => new MockAmazonS3()
        ]);

        $params = [
            'filename' => 'test_file',
            'data'     => 'test data'
        ];
        $closure = $this->_adapter->write($params['filename'], $params['data'], [
            'autoBucket' => false
        ]);

        $fn = function() use ($closure, $params) {
            $closure($this->_adapter, $params);
        };

        $this->assertException('Unexisting S3 bucket `unexisting_bucket`.', $fn);

    }

    public function testRead() {
        $params = [
            'filename' => 'test_file',
        ];
        $closure = $this->_adapter->read($params['filename'], []);
        $closure($this->_adapter, $params);
        $s3 = $this->_adapter->s3();

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'get_object',
            'params' => ['li3_aws', 'test_file', []]
        ], $call);
    }

    public function testDelete() {
        $params = [
            'filename' => 'test_file',
        ];
        $closure = $this->_adapter->delete($params['filename'], []);
        $closure($this->_adapter, $params);
        $s3 = $this->_adapter->s3();

        $call = array_shift($s3->calls);
        $this->assertEqual([
            'method' => 'delete_object',
            'params' => ['li3_aws', 'test_file', []]
        ], $call);
    }

    public function testUrl() {
        $url = $this->_adapter->url('test_file', []);
        $this->assertIdentical('http://li3_aws.s3.amazonaws.com/test_file', $url);
    }

    public function testUrlWithHttpProtocol() {
        $url = $this->_adapter->url('test_file', ['protocol' => 'https']);
        $this->assertIdentical('https://li3_aws.s3.amazonaws.com/test_file', $url);
    }

    public function testUrlWithTwoForwardSlashesProtocol() {
        $url = $this->_adapter->url('test_file', ['protocol' => '']);
        $this->assertIdentical('//li3_aws.s3.amazonaws.com/test_file', $url);
    }

    public function testUrlWithCloudfrontCdn() {
        $url = $this->_adapter->url('test_file', [
            'cdn' => true
        ]);
        $this->assertIdentical('http://li3_aws.cloudfront.net/test_file', $url);
    }

    public function testUrlWithCloudfrontCdnWithNoCloudfrontDefined() {
        $fn = function() {
            $url = $this->_adapter->url('test_file', [
                'cloudfront' => null,
                'cdn' => true
            ]);
        };
        $this->assertException("CDN need a valid domain address for `'cloudfront'` option.", $fn);
    }

    public function testSignS3() {
        $url = $this->_adapter->signUrl('test_file', [
            'timeout' => '26/09/2014 15:24:52'
        ]);
        $expected = 'http://li3_aws.s3.amazonaws.com/test_file';
        $signature = '?AWSAccessKeyId=BKIAJCQJZKAWSTNVBHUJ&Expires=0&Signature=w0%2Bg4Ckt6FtW2fc3%2F7Knn%2Fa2zZA%3D';
        $this->assertIdentical($expected . $signature, $url);
    }

    public function testSignatureOnly() {
        $url = $this->_adapter->signUrl('test_file', [
            'timeout' => '26/09/2014 15:24:52',
            'signatureOnly' => true
        ]);
        $signature = 'AWSAccessKeyId=BKIAJCQJZKAWSTNVBHUJ&Expires=0&Signature=w0%2Bg4Ckt6FtW2fc3%2F7Knn%2Fa2zZA%3D';
        $this->assertIdentical($signature, $url);
    }

}
