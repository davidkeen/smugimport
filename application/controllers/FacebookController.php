<?php

require_once 'facebook.php';

abstract class FacebookController extends Zend_Controller_Action
{
    // Preference ID (deprecated)
    const PREF_SMUG_NICKNAME = 0;
    
    /**
     * EntityManager
     *
     * @var instance of Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    protected $user;

    protected $appId;
    protected $apiKey;
    protected $apiSecret;
    protected $canvasUrl;
    protected $fbUserId;
    protected $logger;

    /**
     * Facebook api
     * @var Facebook
     */
    protected $facebook;

    public function init() {
        $this->logger = Zend_Registry::get('logger');

        // init doctrine entity manager
        $this->entityManager = Application_Api_Util_Bootstrap::getResource('Entitymanagerfactory');

        $config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $this->appId = $config->facebook->appId;
        $this->apiKey = $config->facebook->apiKey;
        $this->apiSecret = $config->facebook->apiSecret;
        $this->canvasUrl = $config->facebook->canvasUrl;

        $this->facebook = new Facebook(array(
            'appId' => $this->appId,
            'api' => $this->apiKey,
            'secret' => $this->apiSecret,
            'cookie' => true));

        $this->fbUserId = $this->facebook->getUser();
        if (is_null($this->fbUserId)) {
            $this->login();
        }
        if (constant('Zend_Log::' . $config->log->priority) == Zend_Log::DEBUG) {
            $me = $this->facebook->api('/me');
            $this->logger->log("me: " . print_r($me, true), Zend_Log::DEBUG);
        }

        if (!Zend_Session::isStarted()) {
            Zend_Session::start();
        }

        parent::init();

        // Set the current SmugImport user
        $this->user = $this->entityManager->getRepository('Application_Model_User')
                ->findOneBy(array('facebookId' => $this->fbUserId));
        
        // If we can't find the user, create them.
        if (!$this->user) {
            $this->user = $this->createUser($this->fbUserId);
            $this->logger->log("init() - Created Facebook user: " . $this->fbUserId, Zend_Log::INFO);
        }
    }

    protected function _redirect($url, array $options = array()) {
        parent::_redirect($url, $options);
    }

    /*
     * Generate a signature using the application secret key.
     *
     * The only two entities that know your secret key are you and Facebook,
     * according to the Terms of Service. Since nobody else can generate
     * the signature, you can rely on it to verify that the information
     * came from Facebook.
     *
     * @param $params_array   an array of all Facebook-sent parameters,
     *                        NOT INCLUDING the signature itself
     * @param $secret         your app's secret key
     *
     * @return a hash to be checked against the signature provided by Facebook
     */
    protected function generate_sig($params_array, $secret) {
        $str = '';

        ksort($params_array);
        // Note: make sure that the signature parameter is not already included in
        //       $params_array.
        foreach ($params_array as $k => $v) {
            $str .= "$k=$v";
        }
        $str .= $secret;

        return md5($str);
    }

    protected function login() {
        $url = $this->facebook->getLoginUrl(array(
            "canvas" => 1,
            "fbconnect" => 0,
            "req_perms" => "user_photos"));
        echo "<script type='text/javascript'>top.location.href = '$url';</script>";
        exit();
    }

    /**
     * Get the SmugMug nickname from either the Facebook pref API or the DB.
     */
    protected function getNickname() {
        // Get the SmugMug nickname
        // First we try the DB
        $nickname = $this->getNicknameFromDatabase();

        // Then try the data api
        // TODO: Remove this check after the api has been switched off.
        if (empty($nickname)) {
            $nickname = $this->getNicknameFromFacebook();
            if (!empty($nickname)) {

                // Migrate it to the database
                $this->user->setNickname($nickname);
                $this->entityManager->flush();
            }
        }

        // By now we either have the nickname or they have never set one.
        return $nickname;
    }

    private function getNicknameFromFacebook() {
        try {
            $params = array(
                'method' => 'data.getUserPreference',
				'uid' => $this->fbUserId,
                'pref_id' => self::PREF_SMUG_NICKNAME);
            $nickname = $this->facebook->api($params);
            $this->logger->log("getNicknameFromFacebook() - " . print_r($nickname, true), Zend_Log::DEBUG);
            return $nickname;
        } catch (Exception $e) {
            // Perhaps they haven't set their nickname yet (or the data api has been turned off)
            // so just log it and return null.
			$this->logger->log("getNicknameFromFacebook() - Error getting nickname: " . $e->getMessage(), Zend_Log::DEBUG);
            return null;
        }
    }

    private function getNicknameFromDatabase() {
        if ($this->user != null) {
            return $this->user->getNickname();
        }
        return null;
    }

    private function createUser($facebookId) {
        $newUser = new Application_Model_User();
        $newUser->setFacebookId($facebookId);
        $newUser->setCreated(new DateTime());
        $this->entityManager->persist($newUser);
        $this->entityManager->flush();
        return $newUser;
    }
}

// Should we be just setting them here or should we add them to the response object?
header('Content-Type: text/html; charset=UTF-8');
header('P3P: CP="CAO PSA OUR"');

