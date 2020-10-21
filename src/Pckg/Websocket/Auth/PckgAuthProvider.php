<?php namespace Pckg\Websocket\Auth;

use Thruway\Authentication\WampCraAuthProvider;

class PckgAuthProvider extends WampCraAuthProvider
{

    public function processAuthenticate($signature, $extra = null)
    {
        return parent::processAuthenticate($signature, $extra);
        
        if ($parent[0] === 'SUCCESS') {
            return $parent;
        }

        $authDetails = [
            'authmethod'   => 'wampcra',
            'authrole'     => 'user',
            'authid'       => 'guestit',
            'authprovider' => 'pckg'
        ];

        return ['SUCCESS', $authDetails];
    }

}