<?php namespace Pckg\Websocket\Environment;

use Pckg\Framework\Environment\Queue;

class Websocket extends Queue
{

    protected $appClass = \Pckg\Websocket\Application\Websocket::class;

}