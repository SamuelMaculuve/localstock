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
                $manager = new ImageManager(new Driver());
                $image = $manager->read($file->getRealPath());
                
                // Limit image dimensions for watermark processing to prevent timeout
                $maxDimension = 4000;
                if ($image->width() > $maxDimension || $image->height() > $maxDimension) {
                    $scale = min($maxDimension / $image->width(), $maxDimension / $image->height());
                    $newWidth = (int)($image->width() * $scale);
                    $newHeight = (int)($image->height() * $scale);
                    $image->resize($newWidth, $newHeight);
                }

                $watermarkPath = $this->getWatermarkImage();
                if (empty($watermarkPath) || !file_exists($watermarkPath)) {
                    Log::warning('Watermark image not available, uploading without watermark');
                    throw new \Exception('Watermark image not found');
                }

                $watermark = $manager->read($watermarkPath);

                // Mantemos a transparência original da imagem da marca (PNG recomendado).
                // Evita artefactos visuais causados por remoção automática de fundo.
                $wmWidth = (int) max(48, $image->width() * 0.12);
                $wmHeight = (int) ($wmWidth * ($watermark->height() / max(1, $watermark->width())));
                $watermark->resize($wmWidth, $wmHeight);

                // Opacidade configurável em Admin → Definições → Application Settings (1–100).
                $opacity = (int) getOption('watermark_opacity', 7);
                $opacity = max(1, min(100, $opacity));

                // Grelha diagonal limpa: desloca metade do passo em linhas alternadas.
                // Isso remove o efeito de colunas verticais.
                $spacingX = (int) max(70, $wmWidth * 1.45);
                $spacingY = (int) max(60, $wmHeight * 1.30);
                $row = 0;

                for ($y = -$wmHeight; $y < $image->height() + $wmHeight; $y += $spacingY) {
                    $rowOffset = (int) (($row % 2) * ($spacingX / 2));
                    for ($x = -$wmWidth + $rowOffset; $x < $image->width() + $wmWidth; $x += $spacingX) {
                        try {
                            $image->place($watermark, 'top-left', $x, $y, $opacity);
                        } catch (\Throwable $e) {
                            // Fallback para API antiga se 'place' não aceitar 5 argumentos
                            $image->place($watermark, $x, $y);
                        }
                    }
                    $row++;
                }

                $tempPath = storage_path('app/temp/' . $file_name);
                if (!file_exists(storage_path('app/temp'))) {
                    mkdir(storage_path('app/temp'), 0777, true);
                }
                $image->save($tempPath);

                Storage::disk(config('app.STORAGE_DRIVER'))
                    ->put($path, file_get_contents($tempPath));

                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

            } catch (\Exception $e) {
                Log::warning('Watermark failed, uploading original: ' . $e->getMessage());
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
        $fileManagerId = getOption('water_mark_img');
        if (empty($fileManagerId)) {
            return null;
        }

        $fileManager = self::find($fileManagerId);
        if (!$fileManager) {
            Log::warning('Watermark FileManager not found: ' . $fileManagerId);
            return null;
        }

        $driver = config('app.STORAGE_DRIVER');
        if ($driver === 'local' || $driver === 'public') {
            $localPath = storage_path('app/public/' . $fileManager->path);
            if (file_exists($localPath)) {
                return $localPath;
            }
            $localPath = public_path('storage/' . $fileManager->path);
            if (file_exists($localPath)) {
                return $localPath;
            }
            Log::warning('Watermark file not found on disk: ' . $fileManager->path);
            return null;
        }

        $url = getSettingImage('water_mark_img');
        return $this->downloadWatermarkFromUrl($url);
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
            if (empty($url)) {
                return null;
            }

            // Se for um path local (ex.: no-image ou path no servidor), devolver se existir
            if (!preg_match('#^https?://#i', $url) && file_exists($url)) {
                return $url;
            }

            $tempPath = storage_path('app/temp/watermark_' . md5($url) . '.png');

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            if (file_exists($tempPath) && (time() - filemtime($tempPath)) < 3600) {
                return $tempPath;
            }

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

            $watermarkContent = @file_get_contents($url, false, $context);

            if ($watermarkContent === false) {
                if (file_exists($tempPath)) {
                    return $tempPath;
                }
                throw new \Exception('Failed to download watermark from URL');
            }

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
