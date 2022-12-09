<?php

namespace Pckg\Websocket\Auth;

use Pckg\Auth\Record\User;

/**
 * Class UserDb
 */
class UserDb implements \Thruway\Authentication\WampCraUserDbInterface
{
    /**
     * @var array
     */
    private $users;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = [];
    }

    /**
     * Add new user
     *
     * @param string $userName
     * @param string $password
     * @param string $salt
     */
    public function add($userName, $password, $salt = null)
    {
        if ($salt !== null) {
            $key = \Thruway\Common\Utils::getDerivedKey($password, $salt);
        } else {
            $key = $password;
        }

        $this->users[$userName] = ["authid" => $userName, "key" => $key, "salt" => $salt];
    }

    /**
     * Get user by username
     *
     * @param string $authId Username
     * @return boolean
     */
    public function get($authId)
    {
        if ($authId === 'guest') {
            return [
                'authid' => 'guest',
                'key' => \Thruway\Common\Utils::getDerivedKey('guest', 'guestsalt'),
                'salt' => 'guestsalt',
                'authrole' => 'guest',
            ];
        } else if ($authId === 'admin') {
            return [
                'authid' => 'admin',
                'key' => \Thruway\Common\Utils::getDerivedKey('admin', 'adminsalt'),
                'salt' => 'adminsalt',
                'authrole' => 'admin',
            ];
        }

        /**
         * Auth id in our case is id:email.
         * Could it be an API key?
         */
        $exploded = explode(':', $authId, 2);
        if (!is_numeric($exploded[0]) || !isValidEmail($exploded[1])) {
            return false;
        }

        /**
         * Fetch user from the database.
         * Should we throw exceptions here?
         */
        try {
            $user = User::gets(['id' => $exploded[0], 'email' => $exploded[1]]);
        } catch (\Throwable $e) {
            error_log('Error fetching user: ' . exception($e));
            return false;
        }

        if (!$user || !$user->autologin) {
            error_log('No user or no autologin');
            return false;
        }

        return [
            'authid' => $authId,
            'key' => \Thruway\Common\Utils::getDerivedKey($user->autologin, auth()->getSecurityHash()),
            'salt' => auth()->getSecurityHash(),
            'authrole' => 'user',
        ];
    }
}
