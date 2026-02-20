<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class NSFWService
{
    // You can set a default endpoint; but note you have two different endpoints in your code.
    // I'll use the one from your detect() method, as it's the correct router URL.
    protected $endpoint = "https://router.huggingface.co/hf-inference/models/Falconsai/nsfw_image_detection";

    public function detect($filePath)
    {
        try {
            // Validate file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new Exception("File not found or not readable: $filePath");
            }

            // --- Step 1: Determine if file is ZIP or image ---
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $isZip = ($fileExtension === 'zip');

            // Alternatively, you could check MIME type with mime_content_type() for more accuracy:
            // $mime = mime_content_type($filePath);
            // $isZip = ($mime === 'application/zip' || $mime === 'application/x-zip-compressed');

            $imageData = null;
            $imageMime = null;

            if ($isZip) {
                // --- Handle ZIP file: extract first image ---
                $zip = new ZipArchive();
                if ($zip->open($filePath) !== true) {
                    throw new Exception("Cannot open ZIP file: $filePath");
                }

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    // Check for common image extensions
                    if (preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $filename)) {
                        $imageData = $zip->getFromIndex($i);
                        $extension = pathinfo($filename, PATHINFO_EXTENSION);
                        $imageMime = $this->getMimeFromExtension($extension);
                        break;
                    }
                }
                $zip->close();

                if (!$imageData) {
                    throw new Exception("No image file found in the ZIP archive.");
                }
            } else {
                // --- Handle direct image file ---
                $imageData = file_get_contents($filePath);
                if ($imageData === false) {
                    throw new Exception("Failed to read image file: $filePath");
                }

                // Detect MIME type from file (more reliable than extension)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $imageMime = finfo_file($finfo, $filePath);
                finfo_close($finfo);

                // If finfo fails, fall back to extension-based MIME
                if (!$imageMime || $imageMime === 'application/octet-stream') {
                    $imageMime = $this->getMimeFromExtension($fileExtension);
                }
            }

            // --- Step 2: Send to Hugging Face API ---
            $token = env('HUGGINGFACE_API_KEY');

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => $imageMime,
            ])->withBody($imageData, $imageMime)->post($this->endpoint);

            Log::info("NSFWService response", [
                'status' => $response->status(),
                'body'   => $response->body()
            ]);

            if ($response->failed()) {
                throw new Exception("API request failed: " . $response->body());
            }

            $data = $response->json();

            // Validate response structure
            if (!is_array($data) || !isset($data[0]['label']) || !isset($data[0]['score'])) {
                throw new Exception("Unexpected API response format: " . json_encode($data));
            }

            // Find the score for the "nsfw" label
            $nsfwScore = null;
            foreach ($data as $item) {
                if ($item['label'] === 'nsfw') {
                    $nsfwScore = $item['score'];
                    break;
                }
            }

            if ($nsfwScore === null) {
                throw new Exception("NSFW label not found in response.");
            }

            return $nsfwScore;

        } catch (Exception $e) {
            Log::error("Error in NSFWService::detect", [
                'message' => $e->getMessage(),
                'file'    => $filePath
            ]);
            return null; // or re-throw depending on your error handling strategy
        }
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
