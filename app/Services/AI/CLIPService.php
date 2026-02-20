<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class CLIPService
{
    protected $endpoint = "https://router.huggingface.co/hf-inference/models/openai/clip-vit-base-patch32";

    public function embed($imagePath)
    {
        // Validate file
        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            throw new Exception("Image file not found or not readable: $imagePath");
        }

        // Open the ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($imagePath) !== true) {
            throw new Exception("Cannot open ZIP file: $imagePath");
        }

        // Find the first image file inside the ZIP
        $imageData = null;
        $imageName = null;
        $imageMime = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            // Check for common image extensions
            if (preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $filename)) {
                // Read the file contents from the ZIP
                $imageData = $zip->getFromIndex($i);
                $imageName = $filename;
                // Determine MIME type based on extension (or use finfo)
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $imageMime = $this->getMimeFromExtension($extension);
                break;
            }
        }

        $zip->close();

        if (!$imageData) {
            throw new Exception("No image file found in the ZIP archive.");
        }

        $image = $imageData;

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . env("HUGGINGFACE_API_KEY"),
        ])->withBody($image, $imageMime)
            ->post($this->endpoint);

            Log::info("CLIPService response", ['status' => $response->status(),
    'body'   => $response->body()]);

        return $response->json(); // contains embedding vector
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
