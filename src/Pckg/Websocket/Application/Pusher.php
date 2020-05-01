<?php namespace Pckg\Websocket\Application;

use Pckg\Framework\Application\Console;
use Pckg\Websocket\Service\Websocket;

class Pusher extends Console
{

    public function runs()
    {
        return [
            function () {
                (new Websocket())->startAuthRouter();
            },
        ];
    }
}