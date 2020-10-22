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
 * Class PublishMessage
 *
 * @package Scintilla\Console
 */
class PublishMessage extends Command
{

    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    public function configure()
    {
        $this->setName('websocket:publish-message')->addOptions([
            'channel' => 'Publish message on channel',
            'event' => 'Publish message on event',
            'data' => 'JSON data'], InputOption::VALUE_REQUIRED);
    }

    /**
     * @param RabbitMQ $rabbitMQ
     */
    public function handle()
    {
        $channel = $this->option('channel') ?? 'test-channel';
        $event = $this->option('event') ?? 'test-event';
        $data = $this->decodeOption('data') ?? 'test-data';
        /**
         * @var $websocket Websocket
         */
        $websocket = resolve(Websocket::class);
        //$websocket->authenticateClient('admin', 'admin');
        $websocket->publish($channel, ['event' => $event, 'data' => $data]);
    }

}