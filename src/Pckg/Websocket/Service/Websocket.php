<?php

namespace Pckg\Websocket\Service;

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
use Thruway\Peer\Client;
use Thruway\Peer\Router;
use Thruway\Realm;
use Thruway\Role\Subscriber;
use Thruway\Transport\RatchetTransportProvider;

/**
 * Class Websocket
 * @package Pckg\Websocket\Service
 *
 * At least one realm needs to be configured on an application router worker in order for WAMP components to be able
 * to connect to it. You can configure multiple realms, e.g. to separate several client applications served by the
 * same application router.
 *
 * Authorization configuration is per realm.
 *
 * Clients are authenticated for a role (this happens at the transport level, see below). You can then configure
 * which actions are allowed for a particular role.
 *
 * The system here is based on URIs, which are used for both subscription topics and registrations. For each role,
 * you can define what actions are allowed for a particular URI. URIs can be matched exactly or pattern-based, and
 * each of the four actions (publish, subscribe, register, call) can be allowed or forbidden separately. You can
 * set a custom authorizer component, which receives information about the attempted action and allows for even more
 * fine-grained authorization management and integration with existing solutions.
 *
 * At least one transport needs to be configured on an application worker in order for WAMP components to be able to
 * connect to it. You can configure multiple transports, e.g. so that some clients can connect via WebSockets and
 * others via RawSocket, or using the same protocol but via different ports.
 *
 * The transport configuration determines which authentication method to require from clients attempting to connect to
 * the transport. Crossbar.io offers several authentication methods, including via HTTP cookie, ticket, a
 * challenge-response mechanism or cryptographic certificates.
 */
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
        $this->options = $options;
    }

    /**
     * Close connection on destruction.
     */
    public function __destruct()
    {
        try {
            $this->closeConnection();
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
        Logger::set(new NullLogger());

        $data = [
            "realm" => $options['realm'] ?? 'realm1',
            "onClose" => function () {
            },
            "url" => $options['scheme'] . '://' . $options['host'] . ':' . $options['port'],
        ];

        if (isset($options['authid'])) {
            $data['authmethods'] = ['wampcra'];
            $data['authid'] = $options['authid'];
        }

        error_log('Connecting to WS ' . json_encode($data));

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

        /**
         *
         */
        $transportProvider = new RatchetTransportProvider($host, $port);
        //$transportProvider->enableKeepAlive($router->getLoop());
        $router->registerModule($transportProvider);

        $router->start();
    }

    /**
     * @param string $bind
     * @param int $port
     * @throws \Exception
     */
    public function startAuthRouter($host = "0.0.0.0", $port = 50445)
    {
        error_log('Websocket@startAuthRouter');
        $router = new Router();

        $this->authenticateRouter($router);

        $this->authorizeRouter($router);

        $transportProvider = new RatchetTransportProvider($host, $port);
        $transportProvider->enableKeepAlive($router->getLoop());
        $router->registerModule($transportProvider);

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
            function_exists('dispatcher') && dispatcher()->trigger('heartbeat');
        });

        /**
         * Single heartbeat mechanysm when there are multiple heartbeats (like in process).
         */
        $lastHeartbeat = time();
        function_exists('dispatcher') && dispatcher()->listen('heartbeat', function () use (&$lastHeartbeat, $heartbeatInterval) {
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
        error_log('Websocket@authenticateRouter');
        $userDb = new UserDb();

        /**
         * Register authentication manager which will take care of authentication.
         */
        $authenticationManager = new AuthenticationManager();
        $router->registerModule($authenticationManager);

        /**
         * We can use multiple realms for different apps or users.
         */
        $realms = config('pckg.websocket.auth.realms', []);
        foreach ($realms as $realm => $realmConfig) {
            error_log('Registering realms ' . json_encode($realms));
        }

        /**
         * One option is to authenticate as user.
         */
        $authProvClient = new PckgAuthProvider(array_keys($realms));
        $authProvClient->setUserDb($userDb);
        $router->addInternalClient($authProvClient);
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
        error_log('Websocket@authorizeRouter ' . json_encode($realms));

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
                $authorizationManager->addAuthorizationRule([(object)$role]);
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
        $topic = str_replace('-', '_', $topic);

        error_log('publishing on ' . $topic . ' for event ' . $data['event']);

        $this->getConnection()->on('open', function (ClientSession $session) use ($topic, $data, $close) {

            error_log('connection opened, publishing');
            error_log("authenticated: " . ($session->getAuthenticated() ? 'yes' : 'no'));

            $session->publish($topic, [json_encode($data)], [], ["acknowledge" => true])->then(
                function () use ($close) {
                    $this->ack();
                    if ($close) {
                        $this->closeConnection();
                    }
                },
                function ($error) use ($close) {
                    $this->nack($error);
                    if ($close) {
                        $this->closeConnection();
                    }
                }
            );
        });

        $this->authenticateClient('admin', 'admin');

        $this->getConnection()->open();
    }

    public function getConnection()
    {
        if (!$this->connection) {
            $this->connection = $this->createConnection($this->options);
        }

        return $this->connection;
    }

    /**
     *
     */
    public function closeConnection()
    {
        if (!$this->connection) {
            return;
        }
        $this->connection->close();
        $this->connection = null;
    }

    /**
     * @param string $user
     * @param string $pass
     * @return $this
     */
    public function authenticateClient($user = 'guest', $pass = 'guest')
    {
        error_log('authenticating client ' . $user . ' ' . $pass);
        $this->getConnection()->getClient()->setAuthId($user);
        $this->getConnection()->getClient()->addClientAuthenticator(new PckgClientAuth($user, $pass));

        return $this;
    }

    /**
     * @param string $topic
     * @param callable $callback
     * @throws \InvalidArgumentException
     */
    public function subscribe(string $topic, callable $callback)
    {
        $this->getConnection()->on('open', function (ClientSession $session) use ($topic, $callback) {
            $session->subscribe($topic, $callback);
        });

        error_log('Opening connection for subscribe');
        $this->getConnection()->open();
    }

    public function register(string $command, callable $callback)
    {
        $this->getConnection()->on('open', function (ClientSession $session) use ($command, $callback) {
            $session->register($command, $callback);
        });

        // $this->authenticateClient();

        error_log('Opening connection for register');
        $this->getConnection()->open();
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
        error_log('Initializing secure WS');
        $loop = \React\EventLoop\Factory::create();

        /**
         * Create a public socket for clients.
         */
        $webSock = new \React\Socket\Server('0.0.0.0:50444', $loop);
        $webSock = new \React\Socket\SecureServer($webSock, $loop, [
            'local_cert' => '/etc/ssl/certs/apache-selfsigned.crt', // path to your cert
            'local_pk' => '/etc/ssl/private/apache-selfsigned.key', // path to your server private key
            'allow_self_signed' => true, // Allow self signed certs (should be false in production)
            'verify_peer' => false
        ]);

        /**
         * Serve public WS over HTTP webserver.
         */
        $webServer = new \Ratchet\Server\IoServer(
            new \Ratchet\Http\HttpServer(
                new \Ratchet\WebSocket\WsServer(
                    $messageComponent
                )
            ),
            $webSock,
            $loop
        );

        /**
         * Create a private socket server.
         */
        $context = new \React\ZMQ\Context($loop);
        $pull = $context->getSocket(\ZMQ::SOCKET_PULL);
        $pull->bind('tcp://127.0.0.1:5555');
        $pull->on('message', function (...$data) use ($messageComponent) {
            error_log('listener got message ' . json_encode($data));
            $messageComponent->forTest('some entry');
        });

        /**
         * Start the loop.
         */
        error_log('Starting secure WS server loop');
        $webServer->run();
    }
}
