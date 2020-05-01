<?php namespace Pckg\Websocket\Auth;

use Thruway\Logging\Logger;

/**
 * Class PckgAuthProviderClient
 * @package Pckg\Websocket\Auth
 */
class PckgAuthProviderClient extends \Thruway\Authentication\AbstractAuthProviderClient
{

    /**
     * @return string
     */
    public function getMethodName()
    {
        return 'pckg';
    }

    /**
     * Process Authenticate message
     *
     * @param mixed $signature
     * @param mixed $extra
     * @return array
     */
    public function processAuthenticate($signature, $extra = null)
    {

        if ($signature == "letMeIn") {
            return ["SUCCESS"];
        } else {
            return ["FAILURE"];
        }

    }

}