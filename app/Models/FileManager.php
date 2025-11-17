<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

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

                $manager = new ImageManager(Driver::class);// Initialize new ImageManager

                // Main image
                $img = $manager->read($file->getRealPath());

                // Load watermark file
                $watermarkPath = $this->getWatermarkImage();
                $watermark = $manager->read($watermarkPath);

                // Resize watermark (5% of main width)
                $wmWidth = (int)($img->width() * 0.05);
                $wmHeight = (int)($wmWidth * ($watermark->height() / $watermark->width()));
                $watermark = $watermark->resize($wmWidth, $wmHeight);

                // Apply pattern across image
                for ($x = 0; $x < $img->width(); $x += $wmWidth + 80) {
                    for ($y = 0; $y < $img->height(); $y += $wmHeight + 80) {
                        $img->place($watermark, 'top-left', $x, $y);
                    }
                }

                // Save temp file
                $tempPath = storage_path('app/temp/' . $file_name);

                if (!file_exists(storage_path('app/temp'))) {
                    mkdir(storage_path('app/temp'), 0777, true);
                }

                $img->save($tempPath);

                // Store file
                Storage::disk(config('app.STORAGE_DRIVER'))
                    ->put($path, file_get_contents($tempPath));

                unlink($tempPath);
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
        if ($watermarkFileId = getOption('water_mark_img')) {
            $fileManager = self::find($watermarkFileId);
            if ($fileManager) {
                $path = "files/Setting/{$fileManager->file_name}";
                if (Storage::disk(config('app.STORAGE_DRIVER'))->exists($path)) {
                    return Storage::disk(config('app.STORAGE_DRIVER'))->path($path);
                }
            }
        }

        return public_path('frontend/assets/img/mask.png');
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
