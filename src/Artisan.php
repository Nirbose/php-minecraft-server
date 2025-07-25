<?php

namespace Nirbose\PhpMcServ;

use Nirbose\PhpMcServ\World\Region;

class Artisan
{
    private static ?Server $server = null;

    public static function setServer(Server $server): void
    {
        self::$server = $server;
    }

    public static function getPlayers(): array
    {
        return self::$server->getPlayers();
    }

    public static function getRegion(): Region
    {
        return self::$server->getRegion();
    }
}