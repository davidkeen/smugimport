<?php

/**
 * View helper to return the application's base URL as defined in the config file.
 * 
 */
class Net_Sharedmemory_View_Helper_BaseUrl
{
    public function baseUrl()
    {
        $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        return $config->app->baseUrl;
    }
}