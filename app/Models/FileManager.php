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
        return getSettingImage('water_mark_img');
    }

    public function removeFile()
    {
        if (Storage::disk(config('app.STORAGE_DRIVER'))->exists($this->path)) {
            Storage::disk(config('app.STORAGE_DRIVER'))->delete($this->path);
            return 100;
        }
        return 200;
    }
}
