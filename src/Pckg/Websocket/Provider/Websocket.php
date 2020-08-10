<?php namespace Pckg\Websocket\Provider;

use Pckg\Framework\Config;
use Pckg\Framework\Provider;
use Pckg\Websocket\Console\PublishMessage;
use Pckg\Websocket\Console\RegisterCommand;
use Pckg\Websocket\Console\SubscribeChannel;

/**
 * Class Websocket
 * @package Pckg\Websocket\Provider
 */
class Websocket extends Provider
{

    /**
     * @return array|\Closure[]
     */
    public function services()
    {
        return [
            \Pckg\Websocket\Service\Websocket::class => function (Config $config) {
                return new \Pckg\Websocket\Service\Websocket($config->get('pckg.websocket.server'));
            }
        ];
    }

    /**
     * @return array|string[]
     */
    public function consoles()
    {
        return [
            RegisterCommand::class,
            PublishMessage::class,
            SubscribeChannel::class,
        ];
    }

}