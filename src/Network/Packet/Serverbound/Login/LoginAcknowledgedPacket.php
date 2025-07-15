<?php

namespace Nirbose\PhpMcServ\Network\Packet\Serverbound\Login;

use Nirbose\PhpMcServ\Network\Packet\Clientbound\Configuration\KnownPacksPacket;
use Nirbose\PhpMcServ\Network\Packet\Clientbound\Configuration\PluginMessagePacket;
use Nirbose\PhpMcServ\Network\Packet\Packet;
use Nirbose\PhpMcServ\Network\Serializer\PacketSerializer;
use Nirbose\PhpMcServ\Network\ServerState;
use Nirbose\PhpMcServ\Session\Session;

class LoginAcknowledgedPacket extends Packet
{
    public function getId(): int
    {
        return 0x03;
    }

    public function read(PacketSerializer $in, string $buffer, int &$offset): void
    {
        // No data to read for this packet
    }

    public function write(PacketSerializer $out): void
    {
        // No data to write for this packet
    }

    public function handle(Session $session): void
    {
        $session->setState(ServerState::CONFIGURATION);

        echo "Login acknowledged, switching to configuration state.\n";
        $session->sendPacket(new KnownPacksPacket());
    }
}