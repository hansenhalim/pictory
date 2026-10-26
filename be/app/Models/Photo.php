<?php

namespace App\Models;

use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $clip_path The behind-the-scenes clip recorded during the countdown before this photo, if the kiosk made one.
 */
#[Fillable(['file_path', 'clip_path', 'ref'])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory;

    /**
     * Get the paper rendered from the same session as this photo.
     *
     * @return BelongsTo<Paper, $this>
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class, 'ref', 'ref');
    }
}
