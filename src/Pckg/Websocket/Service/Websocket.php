<?php namespace Pckg\Websocket\Service;

use Pckg\Websocket\Auth\PckgAuthProviderClient;
use Pckg\Websocket\Auth\PckgClientAuth;
use React\EventLoop\Factory;
use Thruway\Authentication\AuthenticationManager;
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
        $this->connection = new Connection(
            [
                "realm" => 'realm1',
                "onClose" => function () {

                },
                "url" => 'ws://' . $options['host'] . ':' . $options['port'],
            ]
        );
        $this->options = $options;
    }

    /**
     * @param string $host
     * @param int $port
     * @throws \Exception
     */
    public static function startRouter($host = "0.0.0.0", $port = 50445)
    {
        $router = new Router();

        $transportProvider = new RatchetTransportProvider($host, $port);

        $router->addTransportProvider($transportProvider);

        $router->start();
    }

    public function startAuthRouter()
    {
        $loop = Factory::create();
        $router = new Router($loop);

        $this->authenticateRouter($router, $loop);

        $router->registerModule(new RatchetTransportProvider(
            $this->options['host'],
            $this->options['port']
        ));

        $router->start();
    }

    public function authenticateRouter(Router $router, $loop)
    {
        $authenticationManager = new AuthenticationManager();
        $router->registerModule($authenticationManager);

        $realm = new Realm('realm1');
        $realm->addModule($authenticationManager);

        $realmManager = $router->getRealmManager();
        $realmManager->addRealm($realm);
        $realmManager->setAllowRealmAutocreate(false);

        $realm = new Realm('thruway.auth');
        $realmManager->addRealm($realm);

        $authProvClient = new PckgAuthProviderClient(["realm1"], $loop);
        //$authProvClient->setSignatureHandler();

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
        $this->connection->on('open', function (ClientSession $session) use ($client) {

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

        $clientAuth = new PckgClientAuth();
        $this->connection->getClient()->addClientAuthenticator($clientAuth);

        $this->connection->open();
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