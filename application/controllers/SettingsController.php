<?php

class SettingsController extends FacebookController
{
    private $nickname = "";

    public function init() {
        parent::init();

        $this->nickname = $this->getNickname();
    }

    public function indexAction() {
        $this->_forward('settings');
    }

    public function settingsAction() {
        $this->view->fbUserId = $this->fbUserId;

        $form = new Zend_Form;

        $nickname = new Zend_Form_Element_Text('nickname');
        $nickname->setLabel('SmugMug nickname')
                ->setValue($this->nickname)
                ->setRequired(true)
                ->addValidator('NotEmpty');

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Save');

        $form->addElements(array($nickname, $submit));

        if ($this->_request->isPost()) {
            $aFormData = $this->_request->getPost();
            if ($form->isValid($aFormData)) {

                // Save the user's preference
                try {
                    $this->user->setNickname($aFormData['nickname']);
                    $this->entityManager->flush();
                    $this->_helper->FlashMessenger(array('message' => 'Settings updated.', 'level' => 'info'));
                } catch (Exception $e) {
                    $this->logger->log("settingsAction() - Error updating settings: " . $e->getMessage(), Zend_Log::ERR);
                    $this->_helper->FlashMessenger(array('message' => 'Error updating settings', 'level' => 'error'));
                }
            } else {
                $form->populate($aFormData);
            }
        }

        $this->view->form = $form;
    }
}
