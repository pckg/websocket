<?php namespace Pckg\Websocket\Provider;

use Pckg\Database\Record;
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

    /**
     * @return array|\Closure[][]
     */
    public function listeners()
    {
        return [
            \Pckg\Websocket\Service\Websocket::class . ':publish' => [
                function (string $topic, $message, \Pckg\Websocket\Service\Websocket $websocket) {
                    $websocket->publish($topic, [json_encode($message)]);
                }
            ],
            \Pckg\Auth\Service\Auth::class . '.getUserDataArray' => function (Record $user = null, array $data, callable $setter) {
                if (!$user || !$user->autologin) {
                    return;
                }

                $setter([
                    'requestToken' => \Thruway\Common\Utils::getDerivedKey($user->autologin, auth()->getSecurityHash()),
                ]);
            }
        ];
    }

}