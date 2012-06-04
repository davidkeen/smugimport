<?php

/**
 * @Entity
 * @Table(name="user")
 *
 * @author david
 */
class Application_Model_User {

    /**
     * @Id @Column(name="id", type="integer")
     * @GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @Column(name="fb_id", type="string")
     */
    private $facebookId;

    /**
     * @Column(name="smug_id", type="string")
     */
    private $nickname;

    /**
     * @Column(name="created", type="datetime")
     */
    private $created;

    public function getFacebookId() {
        return $this->facebookId;
    }

    public function setFacebookId($fbId) {
        $this->facebookId = $fbId;
    }

    public function getNickname() {
        return $this->nickname;
    }

    public function setNickname($nickname) {
        $this->nickname = $nickname;
    }

    public function getCreated() {
        return $this->created;
    }

    public function setCreated($created) {
        $this->created = $created;
    }
}

