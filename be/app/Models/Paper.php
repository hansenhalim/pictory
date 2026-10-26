<?php

namespace App\Models;

use Database\Factories\PaperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string|null $clip_path The clip paper: the paper as a video, its slots playing their behind-the-scenes clips.
 */
#[Fillable(['file_path', 'clip_path', 'ref', 'frame_id'])]
class Paper extends Model
{
    /** @use HasFactory<PaperFactory> */
    use HasFactory;

    /**
     * Get the frame the session was shot with, unless it was deleted since.
     *
     * @return BelongsTo<Frame, $this>
     */
    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }

    /**
     * Get the photos taken in the same session as this paper.
     *
     * @return HasMany<Photo, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'ref', 'ref');
    }

    /**
     * Get the card the operator assigned this session to, if any.
     *
     * @return BelongsTo<QrCard, $this>
     */
    public function qrCard(): BelongsTo
    {
        return $this->belongsTo(QrCard::class);
    }

    /**
     * Get the short code printed on this paper, if it had one.
     *
     * @return HasOne<ShortCode, $this>
     */
    public function shortCode(): HasOne
    {
        return $this->hasOne(ShortCode::class);
    }

    /**
     * Whether a clip paper can be rendered: the frame still exists, and every one of its slots
     * has a photo with a clip.
     */
    public function canRenderClipPaper(): bool
    {
        return $this->frame !== null
            && $this->photos->count() === count($this->frame->slots)
            && $this->photos->every(fn (Photo $photo): bool => $photo->clip_path !== null);
    }
}
