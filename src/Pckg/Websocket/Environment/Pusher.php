<?php namespace Pckg\Websocket\Environment;

use Pckg\Framework\Application;
use Pckg\Framework\Environment\Queue;

class Pusher extends Queue
{

    public function createApplication(\Pckg\Framework\Helper\Context $context, $appName)
    {
        /**
         * Register app paths, autoloaders and create application provider.
         */
        $appName = $appName ?? ($_SERVER['argv'][1] ?? null);
        $applicationProvider = $this->registerAndBindApplication($context, $appName);

        /**
         * Bind application to context.
         */
        $context->bind(Application::class, $applicationProvider);

        /**
         * Then we create actual application wrapper.
         */
        $application = new \Pckg\Websocket\Application\Pusher($applicationProvider);

        return $application;
    }

}