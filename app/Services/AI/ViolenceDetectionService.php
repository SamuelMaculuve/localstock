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

    /**
     * Map file extension to MIME type.
     */
    private function getMimeFromExtension($extension)
    {
        $mimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'webp' => 'image/webp',
        ];
        return $mimes[strtolower($extension)] ?? 'application/octet-stream';
    }
}
