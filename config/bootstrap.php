<?php

use \lithium\core\Libraries;

/**
 * This is the path to the li3_aws plugin, used for Libraries path resolution
 */
define('LI3_AWS_PATH', dirname(__DIR__));

/**
 * Add the AWS SDK
 */
Libraries::add('AWS', array(
    'path' => LI3_AWS_PATH . '/libraries/aws-sdk-for-php',
    'bootstrap' => 'sdk.class.php',
    'loader' => 'CFLoader::autoloader'
));
