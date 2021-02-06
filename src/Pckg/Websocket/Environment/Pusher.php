<?php

namespace Pckg\Websocket\Environment;

use Pckg\Framework\Application;
use Pckg\Framework\Environment\Queue;

class Pusher extends Queue
{

    protected $appClass = \Pckg\Websocket\Application\Pusher::class;
}
