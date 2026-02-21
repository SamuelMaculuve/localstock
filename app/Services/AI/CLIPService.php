<?php

namespace App\Services\AI;

class CLIPService
{
    /**
     * Embed image locally only (no external API). Returns a dummy embedding for duplicate detection.
     */
    public function embed($imagePath): array
    {
        return array_fill(0, 512, 0.001);
    }
}
