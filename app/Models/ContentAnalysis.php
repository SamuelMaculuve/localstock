<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentAnalysis extends Model
{
    use HasFactory;

    protected $table = 'content_analysis';

    protected $fillable = [
        'file_id',
        'nsfw_score',
        'violence_score',
        'clip_embedding',
        'duplicate_of',
        'is_safe',
    ];

    public function file()
    {
        return $this->belongsTo(FileManager::class, 'file_id');
    }
}
