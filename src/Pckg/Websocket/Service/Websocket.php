<?php namespace Pckg\Websocket\Service;

use Pckg\Database\Repository;
use Pckg\Websocket\Auth\PckgAuthProvider;
use Pckg\Websocket\Auth\PckgClientAuth;
use Pckg\Websocket\Auth\StaticUserDb;
use Pckg\Websocket\Auth\UserDb;
use Psr\Log\NullLogger;
use Ratchet\MessageComponentInterface;
use React\EventLoop\Factory;
use Thruway\Authentication\AnonymousAuthenticator;
use Thruway\Authentication\AuthenticationManager;
use Thruway\Authentication\AuthorizationManager;
use Thruway\Authentication\ClientWampCraAuthenticator;
use Thruway\Authentication\WampCraAuthProvider;
use Thruway\ClientSession;
use Thruway\Connection;
use Thruway\Logging\Logger;
use Thruway\Peer\Router;
use Thruway\Realm;
use Thruway\Role\Subscriber;
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
        $this->options = $options;
        $this->connection = $this->createConnection($options);
    }

    /**
     *
     */
    public function __destruct()
    {
        if (!$this->connection) {
            return;
        }

        try {
            $this->connection->close();
        } catch (\Throwable $e) {
            error_log('Error closing Websocket connection: ' . exception($e));
        }
    }

    /**
     * @param $options
     * @return Connection
     * @throws \Exception
     */
    public function createConnection($options)
    {
        //Logger::set(new NullLogger());

        $data = [
            "realm" => $options['realm'] ?? 'realm1',
            "onClose" => function () {

            },
            "url" => $options['scheme'] . '://' . $options['host'] . ':' . $options['port'],
        ];

        if (isset($options['authid'])) {
            $data['authmethods'] = ['pckg'];
            $data['authid'] = $options['authid'];
        }

        return new Connection($data);
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

        /**
         * Trigger heartbeat to keep long-lived connections open?
         * What would we like to do here? Ping our clients?
         */
        $heartbeatInterval = 30;
        $router->getLoop()->addPeriodicTimer(30, function () use ($router) {
            /**
             * Ping clients?
             */
            error_log("Heartbeat @ " . date('Y-m-d H:i:s') . ", " . ($router->managerGetSessionCount()[0] ?? 0) . " sessions");
            dispatcher()->trigger('heartbeat');
        });

        /**
         * Single heartbeat mechanysm when there are multiple heartbeats (like in process).
         */
        $lastHeartbeat = time();
        dispatcher()->listen('heartbeat', function () use (&$lastHeartbeat, $heartbeatInterval) {
            try {
                if (time() < ($lastHeartbeat + ($heartbeatInterval * 0.9))) {
                    return;
                }

                $lastHeartbeat = time();
                foreach (Repository\RepositoryFactory::getRepositories() as $repository) {
                    /**
                     * @var $repository Repository
                     */
                    if (!method_exists($repository, 'checkThenExecute')) {
                        continue;
                    }

                    /**
                     * Check server status.
                     */
                    $repository->checkThenExecute(function () {
                        error_log('PDO ping made');
                    });
                }
            } catch (\Throwable $e) {
                error_log('EXCEPTION in heartbeat:' . exception($e));
            }
        });

        $router->start();
    }

    public function authenticateRouter(Router $router)
    {
        $userDb = new UserDb();

        $authenticationManager = new AuthenticationManager();
        $router->registerModule($authenticationManager);

        $this->authorizeRouter($router);

        $realms = config('pckg.websocket.auth.realms', []);
        foreach ($realms as $realm => $realmConfig) {
            $authProvClient = new PckgAuthProvider([$realm]);
            $authProvClient->setUserDb($userDb);
            $router->addInternalClient($authProvClient);
        }
    }

    /**
     * @param Router $router
     */
    private function authorizeRouter(Router $router)
    {
        $defaultRealms = [
            'realm1' => [
                'roles' => [],
            ],
        ];

        $realms = config('pckg.websocket.auth.realms', $defaultRealms);
        foreach ($realms as $realm => $config) {
            $authorizationManager = new \Pckg\Websocket\Service\AuthorizationManager($realm);

            /**
             * Disallow all access by default.
             */
            $authorizationManager->flushAuthorizationRules(false);

            /**
             * Add authorization rules.
             */
            foreach ($config['roles'] ?? [] as $role) {
                $authorizationManager->addAuthorizationRule((object)$role);
            }

            $authorizationManager->setReady(true);
            $router->registerModule($authorizationManager);
        }
    }

    /**
     * @param string $topic
     * @param array $data
     * @throws \InvalidArgumentException
     */
    public function publish(string $topic, array $data = [], bool $close = true)
    {
        $client = $this->connection;
        $topic = str_replace('-', '_', $topic);

        error_log('publishing on ' . $topic . ' for event ' . $data['event']);

        $this->connection->on('open', function (ClientSession $session) use ($topic, $data, $client, $close) {

            $session->publish($topic, [json_encode($data)], [], ["acknowledge" => true])->then(
                function () use ($client, $close) {
                    $this->ack();
                    if ($close) {
                        $client->close();
                        $this->connection = null;
                    }
                },
                function ($error) use ($client, $close) {
                    $this->nack($error);
                    if ($close) {
                        $client->close();
                        $this->connection = null;
                    }
                }
            );
        });
        $this->authenticateClient('admin', 'admin');

        $this->connection->open();
    }
    
    public function getConnection() {
        return $this->connection;
    }

    public function authenticateClient($user = 'guest', $pass = 'guest')
    {
        $this->connection->getClient()->addClientAuthenticator(new PckgClientAuth($user, $pass));
    }

    /**
     * @param string $topic
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function subscribe(string $topic, callable $callback)
    {
        $this->connection->on('open', function (ClientSession $session) use ($topic, $callback) {
            $session->subscribe($topic, $callback);
        });

        error_log('Opening connection');
        $this->connection->open();
    }

    public function register(string $command, callable $callback)
    {
        $this->connection->on('open', function (ClientSession $session) use ($command, $callback) {
            $session->register($command, $callback);
        });

        // $this->authenticateClient();

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

    public function registerMessageComponent(MessageComponentInterface $messageComponent)
    {
        d('running secure websocket');
        $loop = \React\EventLoop\Factory::create();

        $webSock = new \React\Socket\Server('0.0.0.0:50444', $loop);
        $webSock = new \React\Socket\SecureServer($webSock, $loop, [
            'local_cert' => '/etc/ssl/certs/apache-selfsigned.crt', // path to your cert
            'local_pk' => '/etc/ssl/private/apache-selfsigned.key', // path to your server private key
            'allow_self_signed' => TRUE, // Allow self signed certs (should be false in production)
            'verify_peer' => FALSE
        ]);

        $webServer = new \Ratchet\Server\IoServer(
            new \Ratchet\Http\HttpServer(
                new \Ratchet\WebSocket\WsServer(
                    $messageComponent
                )
            ),
            $webSock,
            $loop
        );

        $context = new \React\ZMQ\Context($loop);
        $pull = $context->getSocket(\ZMQ::SOCKET_PULL);
        $pull->bind('tcp://127.0.0.1:5555'); // Binding to 127.0.0.1 means the only client that can connect is itself
        $pull->on('message', function (...$data) use ($messageComponent) {
            d('listener', $data);
            $messageComponent->forTest('some entry');
        });

        $webServer->run();
    }

}