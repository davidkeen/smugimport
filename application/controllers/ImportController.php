<?php

require_once 'phpSmug.php';

class ImportController extends FacebookController
{
    // The maximum size of a facebook album.
    const MAX_ALBUM_SIZE = 200;

    // The SmugMug image size to use.
    // See: http://wiki.smugmug.net/display/API/show+1.2.2?method=smugmug.images.get
    // TODO: Make this configurable
    const SMUG_IMAGE_SIZE = 'XLargeURL';

    // If we get these error codes we should try the upload again.
    public static $retryCodes = array(0, 1, 1000);
    private $smug;

    public function init()
    {
        /* Initialize action controller here */
        parent::init();

        $config = new Zend_Config_Ini(
            APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        // Initialise the SmugMug API client.
        $params = array(
            'APIKey' => $config->smugmug->apiKey,
            'AppName' => $config->smugmug->appName);
        $this->smug = new phpSmug($params);

        $params = array(
            'type' => 'fs',
            'cache_dir' => $config->smugmug->cacheDir,
            'cache_expire' => $config->smugmug->cacheExpire);
        $this->smug->enableCache($params);

        // Anonymous for now
        try {
            $this->smug->login();
        } catch (Exception $e) {
            $this->_helper->FlashMessenger(array(
                'message' => 'Error connecting to SmugMug. Please try again later.<br />
                    Check the <a href="http://smugmug.wordpress.com" target="_blank">SmugMug status blog</a> to see if they are performing maintenance.',
                'level' => 'error'));
        }

        $reload = $this->getRequest()->getParam('reload');
        if ($reload) {
            $this->smug->clearCache();
            $this->_helper->FlashMessenger(array(
                'message' => 'Album list refreshed.',
                'level' => 'info'));
        }
    }

    public function indexAction()
    {
        $this->_forward('form');
    }

    public function formAction()
    {
        $error = false;
        $nickname = $this->getNickname();

        if (!empty($nickname)) {
            $this->logger->log("formAction() - Got nickname: " . $nickname, Zend_Log::DEBUG);
        } else {
            $this->_helper->FlashMessenger(array(
                'message' => 'You must enter your SmugMug nickname to import albums.',
                'level' => 'error'));
            $this->_redirect("/settings");
        }

        $form = new Zend_Form;
        $form->setAction('import/import')
            ->setMethod('post')
            ->setAttrib('target', 'process')
            ->setAttrib('onSubmit', 'openDialog();');

        // Make a list of the user's SmugMug albums
        $smugAlbums = array();
        try {
            $smugAlbums = $this->smug->albums_get("NickName={$nickname}");
            usort($smugAlbums, array('ImportController', 'compareSmugMugAlbums'));
        } catch (Exception $e) {
            $this->_helper->FlashMessenger(array(
                'message' => 'Error retrieving SmugMug albums.',
                'level' => 'error'));
            $error = true;
        }
        $smugAlbumSelect = new Zend_Form_Element_Select('smugAlbum');
        $smugAlbumSelect->setLabel('SmugMug Album');
        foreach ($smugAlbums as $album) {
            $smugAlbumSelect->addMultiOption("{$album['id']}__{$album['Key']}", $album['Title']);
        }
        $form->addElement($smugAlbumSelect);

        // Make a list of the user's Facebook albums
        $fbAlbums = array();
        $params = array(
            'method' => 'photos.getAlbums',
            'uid' => $this->fbUserId);
        $fbAlbums = $this->facebook->api($params);

        if (!empty($fbAlbums)) {
            usort($fbAlbums, array('ImportController', 'compareFacebookAlbums'));
        } else {
            $this->_helper->FlashMessenger(array(
                'message' => 'Error retrieving Facebook albums.',
                'level' => 'error'));
            $error = true;
        }
        $fbAlbumSelect = new Zend_Form_Element_Select('fbAlbum');
        $fbAlbumSelect->setLabel('Facebook Album');
        foreach ($fbAlbums as $album) {
            if (!$this->isProfileAlbum($album)) {
                $fbAlbumSelect->addMultiOption($album['aid'], $album['name']);
            }
        }
        if (count($fbAlbumSelect->getMultiOptions()) == 0) {
            $this->_helper->FlashMessenger(array(
                'message' => 'You must <a href="http://www.facebook.com/editalbum.php?new" target="_top">create a Facebook album</a> before you can import photos.',
                'level' => 'error'));
            $error = true;
        }
        $form->addElement($fbAlbumSelect);

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Import');
        if ($error) {
            $submit->setAttrib('disabled', 'disabled');
        }
        $form->addElement($submit);

        $this->view->form = $form;
    }

    public function importAction()
    {
        list($smugAlbumId, $smugAlbumKey) = explode('__', $this->getRequest()->getParam('smugAlbum'));
        $this->logger->log("Importing album ID {$smugAlbumId}, album key {$smugAlbumKey}", Zend_Log::DEBUG);

        // Get list of images and other useful information
        $images = $this->smug->images_get("AlbumID={$smugAlbumId}",
            "AlbumKey={$smugAlbumKey}", "Heavy=1");

        $images = ($this->smug->APIVer == "1.2.2") ? $images['Images'] : $images;
        $totalImages = count($images);

        $fbAlbumId = $this->getRequest()->getParam('fbAlbum');

        if (!$this->albumHasCapacity($fbAlbumId, $totalImages)) {
            $message = "Facebook album has unsufficient capacity.";
            $this->_helper->FlashMessenger(array('message' => $message, 'level' => 'error'));

            // Redirect to default page (import form)
            $this->_redirect();
        }

        $adapter = new Zend_ProgressBar_Adapter_JsPush(array(
            'updateMethodName' => 'Zend_ProgressBar_Update'));
        $progressBar = new Zend_ProgressBar($adapter, 0, count($images));

        $counter = 0;
        $progressBar->update($counter);
        foreach ($images as $image) {
            try {
                $this->uploadPhoto($image[self::SMUG_IMAGE_SIZE], $image['Caption'], $fbAlbumId);
            } catch (Exception $e) {
                $message = "Error uploading photo.<br />Error was: {$e->getCode()} - {$e->getMessage()}";
                $this->_helper->FlashMessenger(array('message' => $message, 'level' => 'error'));

                // Redirect to default page (import form)
                $this->_redirect();
            }
            $progressBar->update(++$counter);
        }
        $progressBar->finish();

        // Publish to the dashboard. (this is still experimental)
//        $activity = array(
//            'message' => '{*actor*} just imported pictures from SmugMug.',
//            'action_link' => array('text' => 'Do something', 'href' => 'http://www.example.com/'));
//        $this->facebook->api_client->dashboard_publishActivity($activity);
        // Redirect to the user's album we just uploaded to.
        $params = array(
            'method' => 'photos.getAlbums',
            'uid' => $this->fbUserId,
            'aids' => array($fbAlbumId));
        $albums = $this->facebook->api($params);

        //$this->_redirect($albums[0]['link']);
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $url = $albums[0]['link'];
        echo "<script type=\"text/javascript\">\ntop.location.href = \"$url\";\n</script>";
    }

    /**
     * Uploads a photo from a given URL to a given Facebook album.
     *
     * @param string $photoUrl the origin image URL.
     * @param string $caption the caption to be used.
     * @param string $albumId the Facebook album ID to upload to.
     * @return string the HTTP link to the uploaded photo.
     */
    private function uploadPhoto($photoUrl, $caption, $albumId)
    {
        $config = new Zend_Config_Ini(
            APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        $args = array(
            'method' => 'photos.upload',
            'v' => '1.0',
            'api_key' => $config->facebook->apiKey,
            'uid' => $this->fbUserId,
            'call_id' => microtime(true),
            'format' => 'JSON',
            'aid' => $albumId,
            'caption' => $caption
        );

        $sig = $this->generate_sig($args, $config->facebook->apiSecret);

        $client = new Zend_Http_Client('http://api.facebook.com/restserver.php');
        $client->setMethod(Zend_Http_Client::POST);
        $client->setParameterPost($args);
        $client->setParameterPost('sig', $sig);

        $imageData = file_get_contents($photoUrl);

        // Content type? Always jpg?
        $client->setFileUpload('test.jpg', 'filename', $imageData, 'image/jpg');

        $success = false;
        $tries = 0;
        $maxTries = 5;
        while ((!$success) && ($tries < $maxTries)) {
            try {
                $this->logger->log("Trying upload. Attempt: " . $tries, Zend_Log::DEBUG);
                $response = $client->request();
                $decoded = Zend_Json::decode($response->getBody());
                if ($decoded['error_code']) {
                    throw new Exception($decoded['error_msg'], $decoded['error_code']);
                }
                $success = true;
                $this->logger->log("uploadPhoto() - Upload successful. Response: " . print_r($decoded, true), Zend_Log::DEBUG);
            } catch (Exception $e) {
                $tries++;
                $this->logger->log("uploadPhoto() - Upload failed. Error was: " . $e->getMessage() . ". Retrying - tries: {$tries}", Zend_Log::WARN);
            }
        }

        if (!$success) {
            $this->logger->log("uploadPhoto() - Upload failed after {$tries} attempts. Aborting.", Zend_Log::ERR);
            throw $e;
        }

        return $decoded['link'];
    }

    /**
     * Checks the facebook album has space.
     *
     * @param string $fbAlbumId
     * @param int $numPhotosToImport
     * @return bool
     */
    private function albumHasCapacity($fbAlbumId, $numPhotosToImport)
    {
        $params = array(
            'method' => 'photos.getAlbums',
            'uid' => $this->fbUserId,
            'aids' => array($fbAlbumId));
        $album = $this->facebook->api($params);
        return $album[0]['size'] + $numPhotosToImport <= self::MAX_ALBUM_SIZE;
    }

    private function isProfileAlbum($fbAlbum)
    {
        // We should check whether an album is the profile album by comparing the
        // album cover pid with the user's profile picture pid but as I can't figure
        // out how to get the profile picture PID we just check the name.
        return $fbAlbum['name'] == 'Profile Pictures';
    }

    /**
     * usort callback function
     *
     * @param <type> $album
     * @param <type> $otherAlbum
     * @return <type>
     */
    static private function compareSmugMugAlbums($album, $otherAlbum)
    {
        return strcmp($album['Title'], $otherAlbum['Title']);
    }

    /**
     * usort callback function
     *
     * @param <type> $album
     * @param <type> $otherAlbum
     * @return <type>
     */
    static private function compareFacebookAlbums($album, $otherAlbum)
    {
        return strcmp($album['name'], $otherAlbum['name']);
    }

}
