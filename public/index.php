<?php

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    realpath(APPLICATION_PATH . '/../library/facebook'),
    realpath(APPLICATION_PATH . '/controllers'),
    get_include_path(),
)));

/** Zend_Application */
require_once 'Zend/Application.php';  

// Autoloader
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Noumenal_');
$autoloader->setFallbackAutoloader(true);

// Logging
$config = new Zend_Config_Ini(
            APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$writer = new Zend_Log_Writer_Stream($config->log->path);
$logger = new Zend_Log($writer);
$filter = new Zend_Log_Filter_Priority(constant('Zend_Log::' . $config->log->priority));
$writer->addFilter($filter);

Zend_Registry::set('logger', $logger);

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV, 
    APPLICATION_PATH . '/configs/application.ini'
);
$application->bootstrap()
            ->run();