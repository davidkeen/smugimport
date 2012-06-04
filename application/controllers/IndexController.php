<?php

require_once 'facebook.php';

class IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->_forward('form', 'import');
    }

    /**
     * Called from Facebook Deauthorize URL.
     * Removes the user from the database.
     */
    public function removeAction()
    {
        // No output
        $this->_helper->viewRenderer->setNoRender(true);

        $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $this->appId = $config->facebook->appId;
        $this->apiKey = $config->facebook->apiKey;
        $this->apiSecret = $config->facebook->apiSecret;
        $this->canvasUrl = $config->facebook->canvasUrl;

        $facebook = new Facebook(array(
            'appId' => $this->appId,
            'api' => $this->apiKey,
            'secret' => $this->apiSecret,
            'cookie' => true));

        $signedRequest = $facebook->getSignedRequest();
        if (!empty($signedRequest)) {
            $entityManager = Application_Api_Util_Bootstrap::getResource('Entitymanagerfactory');
            $user = $entityManager->getRepository('Application_Model_User')
                ->findOneBy(array('facebookId' => $signedRequest['user_id']));

            if ($user != NULL) {
                $entityManager->remove($user);
                $entityManager->flush();

                $logger = Zend_Registry::get('logger');
                $logger->log("removeAction() - Deleted Facebook user: " . $signedRequest['user_id'], Zend_Log::INFO);
            }
        }       
    }
}