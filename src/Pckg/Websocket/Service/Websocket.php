<?php namespace Pckg\Websocket\Service;

use Pckg\Websocket\Auth\PckgAuthProvider;
use Pckg\Websocket\Auth\PckgClientAuth;
use Pckg\Websocket\Auth\UserDb;
use React\EventLoop\Factory;
use Thruway\Authentication\AuthenticationManager;
use Thruway\Authentication\ClientWampCraAuthenticator;
use Thruway\Authentication\WampCraAuthProvider;
use Thruway\ClientSession;
use Thruway\Connection;
use Thruway\Peer\Router;
use Thruway\Realm;
use Thruway\Transport\RatchetTransportProvider;

class Websocket
{

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * Websocket constructor.
     * @param array $options
     * @throws \Exception
     */
    public function __construct($options = [])
    {
        //$client = new Client("realm1");
        //$client->addTransportProvider(new PawlTransportProvider("ws://pusher-runner:50445/"));
        d($options);
        $this->connection = new Connection(
            [
                "realm" => 'realm1',
                "onClose" => function () {

                },
                "url" => 'ws://' . $options['host'] . ':' . $options['port'],
                'authmethods' => ['pckg'],
                'authid' => 'peter',
            ]
        );
        $this->options = $options;
    }

    /**
     * @param string $host
     * @param int $port
     * @throws \Exception
     */
    public function startRouter($host = "0.0.0.0", $port = 50445)
    {
        $router = new Router();

        $transportProvider = new RatchetTransportProvider($host, $port);

        $router->addTransportProvider($transportProvider);

        $router->start();
    }

    public function startAuthRouter($bind = "0.0.0.0", $port = 50445)
    {
        $router = new Router();

        $this->authenticateRouter($router);

        $router->registerModule(new RatchetTransportProvider($bind, $port));

        $router->start();
    }

    public function authenticateRouter(Router $router)
    {
        $userDb = new UserDb();

        $userDb->add('peter', 'secret1', 'salt123');
        $userDb->add('joe', 'secret2', "mmm...salt");

        $authenticationManager = new AuthenticationManager();
        $router->registerModule($authenticationManager);

        $authProvClient = new PckgAuthProvider(["realm1"]);
        $authProvClient->setUserDb($userDb);
        $router->addInternalClient($authProvClient);
    }

    /**
     * @param string $topic
     * @param array $data
     * @throws \InvalidArgumentException
     */
    public function publish(string $topic, array $data = [])
    {
        $client = $this->connection;
        $this->connection->on('open', function (ClientSession $session) use ($topic, $data, $client) {

            d('publishing');
            $session->publish($topic, $data, [], ["acknowledge" => true])->then(
                function () use ($client) {
                    $this->ack();
                    $client->close();
                },
                function ($error) use ($client) {
                    $this->nack($error);
                    $client->close();
                }
            );
        });

        $this->authenticateClient();

        $this->connection->open();
    }

    public function authenticateClient()
    {
        $clientAuth = new PckgClientAuth('peter', 'secret1');

        //$clientAuth = new PckgClientAuth();
        //$clientAuth->setAuthId('peter');
        $this->connection->getClient()->addClientAuthenticator($clientAuth);
    }

    /**
     * @param string $topic
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function subscribe(string $topic, callable $callback)
    {
        $this->connection->on('open', function (ClientSession $session) use ($client, $topic, $callback) {
            $session->subscribe($topic, $callback);
        });

        $this->connection->open();
    }

    public function register(string $command, callable $callback)
    {
        $this->connection->on('open', function (ClientSession $session) use ($command, $callback) {
            $session->register($command, $callback);
        });

        $this->authenticateClient();

        $this->connection->open();
    }

    /**
     *
     */
    public function ack()
    {
        echo "Publish Acknowledged, closing\n";
    }

    /**
     * @param $error
     */
    public function nack($error)
    {
        // publish failed
        echo "Publish Error {$error}\n";
    }

}