<?php namespace Pckg\Websocket\Console;

use Pckg\Framework\Console\Command;
use Pckg\Parser\Driver\Selenium;
use Pckg\Queue\Service\RabbitMQ;
use Pckg\Websocket\Service\Websocket;
use PhpOffice\PhpSpreadsheet\Calculation\Web;
use Scintilla\Parser\AbstractSource;
use Scintilla\Record\Search;
use Symfony\Component\Console\Input\InputOption;
use Thruway\Peer\Client;

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
            'realm' => 'Publish message on realm',
            'user' => 'Authenticate with user',
            'pass' => 'Authenticate with pass',
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
        $realm = $this->decodeOption('realm') ?? 'test-realm';
        $user = $this->decodeOption('user') ?? 'test-user';
        $pass = $this->decodeOption('pass') ?? 'test-pass';

        $client = new Client($realm);

        $client->setAttemptRetry(false);

        $client->addTransportProvider(new \Thruway\Transport\PawlTransportProvider("ws://pusher-runner:50445"));

        $client->on('open', function ($session) use ($channel, $data) {
            $session->publish($channel, [$data]);
        });

        /**
         * This needs to be called before we start the client/session - anytime before that.
         */
        if ($user) {
            $client->addClientAuthenticator(new \Thruway\Authentication\ClientWampCraAuthenticator($user, $pass));
            $client->setAuthId($user);
        }

        $client->start();

        ddd('okay');
        return;

        $websocket = resolve(Websocket::class);
        $websocket->authenticateClient($user, $password);
        $websocket->publish($channel, ['event' => $event, 'data' => $data]);
    }

}