<?php

namespace Pckg\Websocket\Console;

use Pckg\Framework\Console\Command;
use Pckg\Parser\Driver\Selenium;
use Pckg\Queue\Service\RabbitMQ;
use Pckg\Websocket\Service\Websocket;
use Scintilla\Parser\AbstractSource;
use Scintilla\Record\Search;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DumpSupervisord
 *
 * @package Scintilla\Console
 */
class RegisterCommand extends Command
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('websocket:register-command')->addOptions([
            'command' => 'A command to register on the websocket',
            'console' => 'A callback to execute'], InputOption::VALUE_REQUIRED);
    }

    /**
     * @param RabbitMQ $rabbitMQ
     */
    public function handle()
    {
        $command = $this->option('command');
        $console = $this->option('console');
        if (!class_exists($console)) {
            throw new \Exception('No console class ' . $console);
        }
        $websocket = resolve(Websocket::class);
        $websocket->authenticateClient('admin', 'admin');
        $websocket->register($command, function () use ($console) {
            return (new $console())->handle();
        });
    }
}
