<?php namespace Pckg\Websocket\Command;

use Pckg\Websocket\Service\Websocket;

/**
 * Class RunAuthRouter
 * @package Pckg\Websocket\Command
 */
class RunAuthRouter
{

    /**
     * @var Websocket
     */
    protected $websocket;

    /**
     * RunAuthRouter constructor.
     * @param Websocket $websocket
     */
    public function __construct(Websocket $websocket)
    {
        $this->websocket = $websocket;
    }

    /**
     *
     */
    public function execute(callable $next)
    {
        $config = config('pckg.websocket.server', []);
        $this->websocket->startAuthRouter($config['bind'], $config['port']);

        return $next();
    }
}