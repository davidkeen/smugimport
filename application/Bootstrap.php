<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initDoctype()
    {
        $this->bootstrap('view');
        $view = $this->getResource('view');
        $view->doctype('XHTML1_STRICT');
    }


    protected function _initAutoload()
    {
        $autoloader = new Zend_Application_Module_Autoloader(array(
            'namespace' => 'Application_',
            'basePath'  => dirname(__FILE__),
        ));

        // Add resource type for Module Api
        $autoloader->addResourceType('api','api/','Api');

        return $autoloader;
    }
}
