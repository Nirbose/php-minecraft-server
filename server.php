<?php

require_once 'vendor/autoload.php';

use Nirbose\PhpMcServ\Server;

$server = new Server("0.0.0.0", 25565);

$server->start();