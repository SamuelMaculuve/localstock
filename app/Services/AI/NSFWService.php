<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NSFWService
{
    protected $endpoint = "https://api-inference.huggingface.co/models/cyberagent/open-calm-nsfw-image-detection";

    public function detect($imagePath)
    {
        try {
            $url = "https://router.huggingface.co/hf-inference/models/Falconsai/nsfw_image_detection";

            $token = env('HUGGINGFACE_API_KEY');

            // Read the image as binary
            $binary = file_get_contents($imagePath);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'image/jpeg',
            ])->withBody(
                $binary,
                'image/jpeg'
            )->post($url);

            Log::error("response", ['response' => $response]);
            return $response->json();
        } catch (Exception $e) {
            Log::error("Error", ['message' => $e->getMessage()]);
        }
    }
}
