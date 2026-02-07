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
            // Increase execution time for image processing
            set_time_limit(300); // 5 minutes
            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');
            
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();
            
            // Check file size (limit to 50MB for processing)
            if ($size > 50 * 1024 * 1024) {
                throw new \Exception('File size exceeds 50MB limit');
            }

            $file_name = $name
                ? $name . '-' . time() . rand(100000, 9999999) . '.' . $extension
                : rand(000, 999) . time() . '.' . $extension;

            $file_name = str_replace(' ', '_', $file_name);

            $path = 'uploads/' . $to . '/' . $file_name;

            // ---------------------------------------------------------------------
            // WATERMARK LOGIC
            // ---------------------------------------------------------------------
            // Check if watermark should be applied
            // $is_watermark is boolean, getOption('water_mark_img') returns path or null
            $watermarkImage = getOption('water_mark_img');
            if ($is_watermark && !empty($watermarkImage) && !$is_main_file) {

            try {
                // V3: Initialize with driver instance
                $manager = new ImageManager(new Driver());

                // V3: Use read() instead of make()
                $image = $manager->read($file->getRealPath());
                
                // Store original dimensions for center calculation
                $originalWidth = $image->width();
                $originalHeight = $image->height();
                
                // Limit image dimensions for watermark processing to prevent timeout
                $maxDimension = 4000;
                if ($image->width() > $maxDimension || $image->height() > $maxDimension) {
                    $scale = min($maxDimension / $image->width(), $maxDimension / $image->height());
                    $newWidth = (int)($image->width() * $scale);
                    $newHeight = (int)($image->height() * $scale);
                    $image->resize($newWidth, $newHeight);
                }

                // Get watermark path
                $watermarkPath = $this->getWatermarkImage();
                
                Log::info('Watermark check:', [
                    'is_watermark' => $is_watermark,
                    'watermarkPath' => $watermarkPath,
                    'file_exists' => $watermarkPath ? file_exists($watermarkPath) : false,
                    'is_main_file' => $is_main_file
                ]);

                if (empty($watermarkPath) || !file_exists($watermarkPath)) {
                    Log::warning('Watermark image not found, skipping watermark: ' . ($watermarkPath ?? 'null'));
                    // Fallback to regular upload without watermark
                    Storage::disk(config('app.STORAGE_DRIVER'))
                        ->put($path, file_get_contents($file->getRealPath()));
                    // Continue to save DB record - skip watermark processing
                } else {
                    Log::info('Applying watermark to image');
                    // V3: Read watermark
                    $watermark = $manager->read($watermarkPath);

                    // Resize watermark (15% of main width for good visibility)
                    $wmWidth = (int)($image->width() * 0.15);
                    $wmHeight = (int)($wmWidth * ($watermark->height() / $watermark->width()));
                    
                    // Ensure reasonable size - not too small, not too large
                    $minWidth = 150;
                    $maxWidth = (int)($image->width() * 0.30); // Max 30% of image width
                    if ($wmWidth < $minWidth) {
                        $wmWidth = $minWidth;
                        $wmHeight = (int)($wmWidth * ($watermark->height() / $watermark->width()));
                    } elseif ($wmWidth > $maxWidth) {
                        $wmWidth = $maxWidth;
                        $wmHeight = (int)($wmWidth * ($watermark->height() / $watermark->width()));
                    }

                    // V3: Resize watermark
                    $watermark->resize($wmWidth, $wmHeight);

                    // Tile watermark across the ENTIRE image with consistent spacing
                    $imgWidth = $image->width();
                    $imgHeight = $image->height();

                    // Define a grid so it always covers the full image (not just the top)
                    $cols = 5;
                    $rows = 4;
                    $stepX = (int)($imgWidth / $cols);
                    $stepY = (int)($imgHeight / $rows);

                    // Ensure the watermark fits inside the grid cell with clear gaps
                    $maxWmWidth = (int)($stepX * 0.6);
                    $maxWmHeight = (int)($stepY * 0.6);

                    if ($wmWidth > $maxWmWidth) {
                        $wmWidth = $maxWmWidth;
                        $wmHeight = (int)($wmWidth * ($watermark->height() / $watermark->width()));
                    }
                    if ($wmHeight > $maxWmHeight) {
                        $wmHeight = $maxWmHeight;
                        $wmWidth = (int)($wmHeight * ($watermark->width() / $watermark->height()));
                    }

                    $watermark->resize($wmWidth, $wmHeight);

                    $rowIndex = 0;
                    // Start at y=0 to ensure a visible top row (not cropped off)
                    for ($y = 0; $y <= $imgHeight + $wmHeight; $y += $stepY) {
                        $offsetX = ($rowIndex % 2 === 0) ? 0 : (int)($stepX / 2);
                        for ($x = (int)(-$wmWidth / 2) + $offsetX; $x <= $imgWidth + $wmWidth; $x += $stepX) {
                            // Use explicit position to avoid mis-ordered parameters (Intervention v3)
                            $image->place($watermark, 'top-left', $x, $y);
                        }
                        $rowIndex++;
                    }

                    Log::info('Tiled watermark applied successfully', [
                        'watermark_size' => $wmWidth . 'x' . $wmHeight,
                        'image_size' => $imgWidth . 'x' . $imgHeight,
                        'step' => $stepX . 'x' . $stepY
                    ]);

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
                } // End of watermark processing else block

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
        try {
            // Get watermark file ID from settings
            $watermarkFileId = getOption('water_mark_img');
            
            if (empty($watermarkFileId)) {
                Log::warning('Watermark file ID not found in settings');
                return null;
            }
            
            // Get FileManager record
            $fileManager = FileManager::find($watermarkFileId);
            
            if (!$fileManager) {
                Log::warning('Watermark FileManager record not found: ' . $watermarkFileId);
                return null;
            }
            
            // Check storage type and get file path
            $storageDriver = config('app.STORAGE_DRIVER');
            
            if ($storageDriver == 'public' || $storageDriver == 'local') {
                // For local storage, return the full path
                $localPath = storage_path('app/public/' . $fileManager->path);
                if (file_exists($localPath)) {
                    return $localPath;
                }
                // Try alternative path
                $localPath = public_path('storage/' . $fileManager->path);
                if (file_exists($localPath)) {
                    return $localPath;
                }
                Log::warning('Watermark file not found locally: ' . $fileManager->path);
            } else {
                // For S3/Wasabi, download from URL
                $watermarkUrl = getSettingImage('water_mark_img');
                return $this->downloadWatermarkFromUrl($watermarkUrl);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting watermark image: ' . $e->getMessage());
            return null;
        }
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
        // If URL is empty or null, return null
        if (empty($url)) {
            return null;
        }
        
        // If URL is a local path, return it directly
        if (file_exists($url)) {
            return $url;
        }
        
        $tempPath = storage_path('app/temp/watermark_' . md5($url) . '.png');

        // Create temp directory if it doesn't exist
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // Check if we already have a cached version (less than 24 hours old)
        if (file_exists($tempPath) && (time() - filemtime($tempPath)) < 86400) {
            return $tempPath;
        }

        // Download from URL using Guzzle or file_get_contents with context
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'timeout' => 10, // Reduced timeout to 10 seconds
                'ignore_errors' => true
            ]
        ]);

        $watermarkContent = @file_get_contents($url, false, $context);

        if ($watermarkContent === false) {
            // If download fails, try to use cached version even if old
            if (file_exists($tempPath)) {
                Log::warning('Watermark download failed, using cached version: ' . $url);
                return $tempPath;
            }
            throw new \Exception('Failed to download watermark from URL: ' . $url);
        }

        // Save to temporary file
        file_put_contents($tempPath, $watermarkContent);

        return $tempPath;

    } catch (\Exception $e) {
        Log::error('Error downloading watermark from URL: ' . $e->getMessage());
        // Try to return cached version if exists
        $tempPath = storage_path('app/temp/watermark_' . md5($url) . '.png');
        if (file_exists($tempPath)) {
            return $tempPath;
        }
        return null;
    }
}

}
