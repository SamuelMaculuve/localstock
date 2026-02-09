<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class CLIPService
{
    protected $endpoint = "https://api-inference.huggingface.co/models/openai/clip-vit-base-patch32";

    public function embed($imagePath)
    {
        $image = file_get_contents($imagePath);

        $response = Http::withHeaders([
            "Authorization" => "Bearer " . env("HUGGINGFACE_API_KEY"),
        ])->withBody($image, 'application/octet-stream')
            ->post($this->endpoint);

        return $response->json(); // contains embedding vector
    }
}
