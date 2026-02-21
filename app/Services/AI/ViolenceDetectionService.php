<?php

namespace App\Services\AI;

class ViolenceDetectionService
{
    /**
     * Analyse image locally only (no external API). Returns safe default score 0.
     */
    public function detect($imagePath): float
    {
        return 0.0;
    }
}
