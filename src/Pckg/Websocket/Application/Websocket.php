<?php namespace Pckg\Websocket\Application;

use Pckg\Framework\Application\Console;
use Pckg\Websocket\Service\MessageComponent;
use Pckg\Websocket\Service\Websocket as WebsocketService;

class Websocket extends Console
{

    public function runs()
    {
        return [
            function () {
                (new WebsocketService([
                    'scheme' => 'ws',
                    'bind' => dotenv('WEBSOCKET_BIND', '0.0.0.0'),
                    'host' => dotenv('WEBSOCKET_HOST', 'pusher-runner'),
                    'port' => dotenv('WEBSOCKET_PORT', 50445),
                ]))->registerMessageComponent(new MessageComponent());
            }
        ];
    }

}