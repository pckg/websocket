<?php namespace Pckg\Websocket\Provider;

use Pckg\Framework\Config;
use Pckg\Framework\Provider;

class Websocket extends Provider
{

    public function services()
    {
        return [
            \Pckg\Websocket\Service\Websocket::class => function (Config $config) {
                return new \Pckg\Websocket\Service\Websocket($config->get('pckg.websocket.server'));
            }
        ];
    }

}