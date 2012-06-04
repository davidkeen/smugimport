<?php
/**
 * Noumenal PHP Library.
 *
 * PHP classes built on top of Zend Framework. (http://framework.zend.com/)
 *
 * Bug Reports: support@noumenal.co.uk
 * Questions  : https://noumenal.fogbugz.com/default.asp?noumenal
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file noumenal-new-bsd-licence.txt.
 * It is also available through the world-wide-web at this URL:
 *
 * http://noumenal.co.uk/license/new-bsd
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@noumenal.co.uk so we can send you a copy immediately.
 *
 * ATTRIBUTION
 *
 * Beyond maintaining the Copyright Notice and Licence, attribution is
 * appreciated but not required. Please attribute where appropriate by
 * linking to:
 *
 * http://noumenal.co.uk/
 *
 * @package    Noumenal
 * @author     Carlton Gibson <carlton.gibson@noumenal.co.uk>
 * @copyright  Copyright (c) 2009 Noumenal Software Ltd. (http://noumenal.co.uk/)
 * @license    http://noumenal.co.uk/license/new-bsd     New BSD License
 * @version    $Revision: 3 $ $Date: 2009-08-13 16:02:49 +0100 (Thu, 13 Aug 2009) $
 * @see        http://www.zfsnippets.com/snippets/view/id/37/grouped-flashmessenger-view-helper
 */
/**
 * View Helper to Display Flash Messages.
 *
 * Checks for messages from previous requests and from the current request.
 *
 * Array format: array('message' => 'My message', 'level' => 'message_level')
 * Valid levels are: info, error, blue, grey (anything really, see css classes.)
 *
 * NOTE: MESSAGES ARE PRESUMED TO BE SAFE HTML. IF REDISPLAYING USER
 * INPUT, ESCAPE ALL MESSAGES PRIOR TO ADDING TO FLASHMESSENGER.
 *
 * @package Noumenal_View
 */
class Noumenal_View_Helper_FlashMessenger extends Zend_View_Helper_Abstract
{
    /**
     * @var Zend_Controller_Action_Helper_FlashMessenger
     */
    private $_flashMessenger = null;

    /**
     * Display Flash Messages.
     *
     * @param  string $key Message level for string messages
     * @param  string $template Format string for message output
     * @return string Flash messages formatted for output
     */
    public function flashMessenger()
    {
        $flashMessenger = $this->_getFlashMessenger();

        //get messages from previous requests
        $messages = $flashMessenger->getMessages();

        //add any messages from this request
        if ($flashMessenger->hasCurrentMessages()) {
            $messages = array_merge(
                $messages,
                $flashMessenger->getCurrentMessages()
            );
            //we don't need to display them twice.
            $flashMessenger->clearCurrentMessages();
        }
        
        //initialise return string
        $output ='';

        $statMessages = array();

        // Takes the message arrays (formatted as:
        // 
        // and puts them into an array of the form:
        //    Array(
        //        [level1] => Array(
        //            [0] => "Message 1"
        //            [1] => "Message 2"
        //        ),
        //        [level2] => Array(
        //            [0] => "Message 1"
        //            [1] => "Message 2"
        //        )
        //        ....
        //    )
        foreach ($messages as $message) {
            if (!array_key_exists($message['level'], $statMessages)) {
                $statMessages[$message['level']] = array();
            }

            array_push($statMessages[$message['level']], $message['message']);
        }

        // Formats messages for HTML output.
        foreach ($statMessages as $level => $messages) {
            $output .= '<div class="fb' . $level . 'box" style="width: 500px;">';

            // If there is only one message to look at, we don't need to deal with
            // ul or li - just output the message into the div.
            if (count($messages) == 1)
                $output .=  $messages[0];

            // If there are more than one message, format it in the fashion of the
            // sample output above.
            else {
                $output .= '<ul>';
                foreach ($messages as $message)
                    $output .= '<li>' . $message . '</li>';
                $output .= '</ul>';
            }

            $output .= '</div>';
        }
        
        return $output;
    }

    /**
     * Lazily fetches FlashMessenger Instance.
     *
     * @return Zend_Controller_Action_Helper_FlashMessenger
     */
    public function _getFlashMessenger()
    {
        if (null === $this->_flashMessenger) {
            $this->_flashMessenger =
                Zend_Controller_Action_HelperBroker::getStaticHelper(
                    'FlashMessenger');
        }
        return $this->_flashMessenger;
    }
}
