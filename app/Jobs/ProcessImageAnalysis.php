<?php

namespace App\Jobs;

use App\Models\ContentAnalysis;
use App\Models\FileManager;
use App\Services\AI\CLIPService;
use App\Services\AI\NSFWService;
use App\Services\AI\ViolenceDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessImageAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $file;

    public function __construct(FileManager $file)
    {
        $this->file = $file;
    }

    public function handle(
        NSFWService $nsfw
    ) {
        Log::error("Starting image analysis for file ID: {$this->file->path}");
        $fullPath = Storage::disk(config('app.STORAGE_DRIVER'))->path($this->file->path);
        // Run models
        $nsfwScore = $nsfw->detect($fullPath);
        // $violenceScore = $violence->detect($fullPath);
        // $embedding = $clip->embed($fullPath);
        Log::error("NSFW message", ['nsfw_score' => $nsfwScore]);
        // // Duplicate detection
        // $duplicateId = $this->findDuplicate($embedding);

        // Save results
        ContentAnalysis::create([
            'file_id'        => $this->file->id,
            'nsfw_score'     => $nsfwScore,
            'is_safe'        => $nsfwScore < 0.25
        ]);
    }

    public function findDuplicate($newEmbedding)
    {
        $threshold = 0.90;

        $images = ContentAnalysis::select('id', 'clip_embedding', 'image_id')->get();

        foreach ($images as $img) {
            $existing = json_decode($img->clip_embedding);

            $similarity = $this->cosineSimilarity($existing, $newEmbedding);

            if ($similarity >= $threshold) {
                return $img->image_id; // duplicate found
            }
        }

        return null; // no duplicates
    }

    public function cosineSimilarity($a, $b)
    {
        $dot = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
