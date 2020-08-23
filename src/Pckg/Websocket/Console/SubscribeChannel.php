<?php namespace Pckg\Websocket\Console;

use Pckg\Framework\Console\Command;
use Pckg\Parser\Driver\Selenium;
use Pckg\Queue\Service\RabbitMQ;
use Pckg\Websocket\Service\Websocket;
use PhpOffice\PhpSpreadsheet\Calculation\Web;
use Scintilla\Parser\AbstractSource;
use Scintilla\Record\Search;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class SubscribeChannel
 *
 * @package Scintilla\Console
 */
class SubscribeChannel extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('websocket:subscribe-channel')->addOptions([
            'channel' => 'Publish message on channel'], InputOption::VALUE_REQUIRED);
    }

    /**
     * @param RabbitMQ $rabbitMQ
     */
    public function handle()
    {
        $channel = $this->option('channel');
        $event = $this->option('event');
        $data = $this->option('data');
        /**
         * @var $websocket Websocket
         */
        $websocket = resolve(Websocket::class);
        $websocket->authenticateClient(); // as guest
        $websocket->subscribe($channel, function ($arg) {
            $this->outputDated('NEW MESSAGE ON CHANNEL:' . json_encode($arg));
        });
    }

}