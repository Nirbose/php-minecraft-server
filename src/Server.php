<?php

namespace Nirbose\PhpMcServ;

use Exception;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Nirbose\PhpMcServ\Entity\Player;
use Nirbose\PhpMcServ\Event\Event;
use Nirbose\PhpMcServ\Event\EventBinding;
use Nirbose\PhpMcServ\Event\EventManager;
use Nirbose\PhpMcServ\Event\Listener;
use Nirbose\PhpMcServ\Listener\PlayerJoinListener;
use Nirbose\PhpMcServ\Manager\KeepAliveManager;
use Nirbose\PhpMcServ\Session\Session;
use Nirbose\PhpMcServ\World\Region;
use Nirbose\PhpMcServ\World\RegionLoader;
use ReflectionClass;
use ReflectionMethod;

class Server
{
    private array $clients = [];
    private array $sessions = [];
    private array $players = [];
    private EventManager $eventManager;
    private int $entityIdCounter = 0;

    private static Logger|null $logger = null;
    private static string $logFormat = "[%datetime%] %level_name%: %message%\n";
    private Region $region;
    private int $maxPlayer = 20;

    public function __construct(
        private readonly string $host,
        private readonly int    $port)
    {
        $this->eventManager = new EventManager();
    }

    public function start(): void
    {
        Artisan::setServer($this);
        $this->region = RegionLoader::load(ROOT_PATH . "/mca-test/r.0.0.mca");

        $socket1 = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket1, $this->host, $this->port);
        socket_listen($socket1);
        self::getLogger()->info("Serveur démarré sur {$this->host}:{$this->port}");

        $this->registerListener(new PlayerJoinListener());

        $write = $except = null;
        $keepAliveManager = new KeepAliveManager();

        while (true) {
            $keepAliveManager->tick($this);
            $read = array_merge([$socket1], $this->clients);
            socket_select($read, $write, $except, null);

            foreach ($read as $socket) {
                if ($socket === $socket1) {
                    $client = socket_accept($socket1);

                    if ($client) {
                        socket_set_nonblock($client);
                        $this->clients[] = $client;
                        $this->sessions[spl_object_id($client)] = new Session($this, $client);
                        echo "Nouveau client connecté.\n";
                    }
                    continue;
                } else {
                    $data = @socket_read($socket, 2048);
                    $id = spl_object_id($socket);

                    if ($data === '' || $data === false) {
                        unset($this->clients[array_search($socket, $this->clients, true)]);
                        $this->clients = array_values($this->clients);
                        $this->sessions[$id]->close();
                        unset($this->sessions[$id]);
                        continue;
                    }

                    if (!isset($this->sessions[$id])) continue;

                    /** @var Session $session */
                    $session = $this->sessions[$id];
                    $session->buffer .= $data;

                    // echo "Paquet brut reçu (hex) : " . bin2hex($data) . "\n";

                    $session->handle();
                }
            }
        }
    }

    public function incrementAndGetId(): int
    {
        return $this->entityIdCounter++;
    }

    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            $formatter = new LineFormatter(self::$logFormat, 'Y-m-d H:i:s');
            self::$logger = new Logger('server');
            $handler = new StreamHandler('php://stdout', Level::Debug);

            $handler->setFormatter($formatter);
            self::$logger->pushHandler($handler);
        }

        return self::$logger;
    }

    /**
     * Add new player
     *
     * @param Player $player
     * @return void
     */
    public function addPlayer(Player $player): void
    {
        $this->players[$this->incrementAndGetId()] = $player;
    }

    /**
     * Get all players connected to the server.
     *
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function getPlayerByUUID(string $uuid): ?Player
    {
        foreach ($this->players as $player) {
            if ($player->getUUID() === $uuid) {
                return $player;
            }
        }

        return null;
    }

    /**
     * @return Region
     */
    public function getRegion(): Region
    {
        return $this->region;
    }

    /**
     * Register listener
     *
     * @param Listener $listener
     * @return void
     * @throws Exception
     */
    public function registerListener(Listener $listener): void
    {
        $reflexionClass = new ReflectionClass($listener);

        foreach ($reflexionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(EventBinding::class);

            if (empty($attributes)) {
                continue;
            }

            $parameters = $method->getParameters();

            if (count($parameters) > 1) {
                throw new Exception("Require one parameter"); // TODO: Change exception
            }

            $isEventChild = get_parent_class($parameters[0]->getType()->getName()) === Event::class;

            if (!$isEventChild) {
                throw new Exception("Parameter is not instance of Event"); // TODO: Change exception
            }

            $this->eventManager->register($parameters[0]->getType()->getName(), $method->getClosure($listener));
        }
    }

    /**
     * @return int
     */
    public function getMaxPlayer(): int
    {
        return $this->maxPlayer;
    }

    /**
     * @param int $maxPlayer
     */
    public function setMaxPlayer(int $maxPlayer): void
    {
        $this->maxPlayer = $maxPlayer;
    }
}