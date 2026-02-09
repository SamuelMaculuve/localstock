<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileManager extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'file_type', 'storage_type', 'original_name', 'file_name', 'user_id', 'path', 'extension', 'size', 'external_link',
    ];

    public function upload($to, $file, $name = null, $id = null, $is_watermark = false, $is_main_file = false)
    {
        try {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();

            $file_name = $name
                ? $name . '-' . time() . rand(100000, 9999999) . '.' . $extension
                : rand(000, 999) . time() . '.' . $extension;

            $file_name = str_replace(' ', '_', $file_name);

            $path = 'uploads/' . $to . '/' . $file_name;

            // ---------------------------------------------------------------------
            // WATERMARK LOGIC
            // ---------------------------------------------------------------------
            if ($is_watermark && getOption('water_mark_img') && !$is_main_file) {

            try {
                // V3: Initialize with driver instance
                $manager = new ImageManager(new Driver());

                // V3: Use read() instead of make()
                $image = $manager->read($file->getRealPath());

                // Get watermark path
                $watermarkPath = $this->getWatermarkImage();

                if (!file_exists($watermarkPath)) {
                    throw new \Exception('Watermark image not found: ' . $watermarkPath);
                }

                // V3: Read watermark
                $watermark = $manager->read($watermarkPath);

                // Resize watermark (5% of main width)
                $wmWidth = (int)($image->width() * 0.05);
                $wmHeight = (int)($wmWidth * ($watermark->height() / $watermark->width()));

                // V3: Resize watermark
                $watermark->resize($wmWidth, $wmHeight);

                // V3: Apply pattern using place() instead of insert()
                for ($x = 0; $x < $image->width(); $x += $wmWidth + 80) {
                    for ($y = 0; $y < $image->height(); $y += $wmHeight + 80) {
                        $image->place($watermark, $x, $y);
                    }
                }

                // Save to temporary file
                $tempPath = storage_path('app/temp/' . $file_name);
                if (!file_exists(storage_path('app/temp'))) {
                    mkdir(storage_path('app/temp'), 0777, true);
                }

                // V3: Save the image
                $image->save($tempPath);

                // Store in final location
                Storage::disk(config('app.STORAGE_DRIVER'))
                    ->put($path, file_get_contents($tempPath));

                // Clean up
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

            } catch (\Exception $e) {
                Log::warning('Watermark failed, uploading original: ' . $e->getMessage());
                // Fallback to regular upload
                Storage::disk(config('app.STORAGE_DRIVER'))
                    ->put($path, file_get_contents($file->getRealPath()));
            }

        }
            // ---------------------------------------------------------------------
            // NO WATERMARK - just upload normally
            // ---------------------------------------------------------------------
            else {
                Storage::disk(config('app.STORAGE_DRIVER'))
                    ->put($path, file_get_contents($file->getRealPath()));
            }

            // ---------------------------------------------------------------------
            // SAVE DB RECORD
            // ---------------------------------------------------------------------
            $fileManager = is_null($id) ? new self() : self::find($id) ?? new self();
            $fileManager->file_type = $file->getMimeType();
            $fileManager->storage_type = config('filesystems.default');
            $fileManager->original_name = $originalName;
            $fileManager->file_name = $file_name;
            $fileManager->user_id = auth()->id() ?? null;
            $fileManager->path = $path;
            $fileManager->extension = $extension;
            $fileManager->size = $size;
            $fileManager->save();

            dispatch(new \App\Jobs\ProcessImageAnalysis($fileManager));

            return [
                'status' => true,
                'file' => $fileManager,
                'message' => "File Saved Successfully"
            ];

        } catch (\Exception $exception) {
            Log::error('File Upload Error: ' . $exception->getMessage());
            return [
                'status' => false,
                'file' => [],
                'message' => $exception->getMessage()
            ];
        }
    }

    private function getWatermarkImage()
    {
        $watermarkPathFromDB = getSettingImage('water_mark_img');
        return $this->downloadWatermarkFromUrl($watermarkPathFromDB);

    }

    public function removeFile()
    {
        if (Storage::disk(config('app.STORAGE_DRIVER'))->exists($this->path)) {
            Storage::disk(config('app.STORAGE_DRIVER'))->delete($this->path);
            return 100;
        }
        return 200;
    }

    protected function downloadWatermarkFromUrl($url)
{
    try {
        $tempPath = storage_path('app/temp/watermark_' . md5($url) . '.png');

        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Check if we already have a cached version (less than 1 hour old)
        if (file_exists($tempPath) && (time() - filemtime($tempPath)) < 3600) {
            return $tempPath;
        }

        // Download from URL using Guzzle or file_get_contents with context
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ]);

        $watermarkContent = file_get_contents($url, false, $context);

        if ($watermarkContent === false) {
            throw new \Exception('Failed to download watermark from URL');
        }

        // Save to temporary file
        file_put_contents($tempPath, $watermarkContent);

        return $tempPath;

    } catch (\Exception $e) {
        Log::error('Error downloading watermark from URL: ' . $e->getMessage());
        return null;
    }
}

public function analysis()
{
    return $this->hasOne(ContentAnalysis::class, 'file_id');
}


}
