<?php

namespace Nirbose\PhpMcServ\Block\Data;

use Nirbose\PhpMcServ\Block\AttachedFace;

trait FaceAttachable
{
    protected AttachedFace $face = AttachedFace::FLOOR;

    public function getAttachedFace(): AttachedFace
    {
        return $this->face;
    }

    public function setAttachedFace(AttachedFace $face): void
    {
        $this->face = $face;
    }
}