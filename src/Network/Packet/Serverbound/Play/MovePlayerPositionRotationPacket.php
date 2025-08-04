<?php

namespace Nirbose\PhpMcServ\Network\Packet\Serverbound\Play;

use Nirbose\PhpMcServ\Network\Packet\Clientbound\Play\MoveEntityPosRotPacket;
use Nirbose\PhpMcServ\Network\Packet\Clientbound\Play\RotateHeadPacket;
use Nirbose\PhpMcServ\Network\Packet\Packet;
use Nirbose\PhpMcServ\Network\Serializer\PacketSerializer;
use Nirbose\PhpMcServ\Session\Session;

class MovePlayerPositionRotationPacket extends Packet
{

    private float $x;
    private float $feetY;
    private float $z;
    private float $yaw;
    private float $pitch;
    private bool $flags;

    public function getId(): int
    {
        return 0x1D;
    }

    public function read(PacketSerializer $serializer, string $buffer, int &$offset): void
    {
        $this->x = $serializer->getDouble($buffer, $offset);
        $this->feetY = $serializer->getDouble($buffer, $offset);
        $this->z = $serializer->getDouble($buffer, $offset);
        $this->yaw = $serializer->getFloat($buffer, $offset);
        $this->pitch = $serializer->getFloat($buffer, $offset);
        $this->flags = $serializer->getByte($buffer, $offset);
    }

    public function write(PacketSerializer $serializer): void
    {
        // This packet is not meant to be sent
        throw new \Exception("MovePlayerPositionRotationPacket cannot be sent");
    }

    public function handle(Session $session): void
    {
        $player = $session->getPlayer();
        $loc = $player->getLocation();

        $factor = 4096;

        $deltaX = (int)(($this->x - $loc->getX()) * $factor);
        $deltaY = (int)(($this->feetY - $loc->getY()) * $factor);
        $deltaZ = (int)(($this->z - $loc->getZ()) * $factor);

        $maxDelta = 32767; // max short
        if (abs($deltaX) > $maxDelta || abs($deltaY) > $maxDelta || abs($deltaZ) > $maxDelta) {
            // Utiliser TeleportEntityPacket à la place
            return;
        }

        $loc->setX($this->x);
        $loc->setY($this->feetY);
        $loc->setZ($this->z);
        $loc->setYaw($this->yaw);
        $loc->setPitch($this->pitch);

        $outPacket = new MoveEntityPosRotPacket(
            $player->getId(),
            $deltaX,
            $deltaY,
            $deltaZ,
            $this->yaw,
            $this->pitch,
            false
        );
        $headRotatePacket = new RotateHeadPacket($player);

        foreach ($session->getServer()->getPlayers() as $player) {
            if ($player->getUuid() === $session->getPlayer()->getUuid()) {
                continue;
            }

            $player->sendPacket($outPacket);
            $player->sendPacket($headRotatePacket);
        }
    }
}