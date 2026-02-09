<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class ViolenceDetectionService
{
    protected $endpoint = "https://api-inference.huggingface.co/models/nateraw/violence-classification";

    public function detect($imagePath)
    {
        $image = file_get_contents($imagePath);

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . env("HUGGINGFACE_API_KEY"),
        ])->withBody($image, 'application/octet-stream')
            ->post($this->endpoint);

        return $response->json();
    }
}
