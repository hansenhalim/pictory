<?php

namespace App\Models;

use Database\Factories\PaperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string|null $clip_path The clip paper: the paper as a video, its slots playing their behind-the-scenes clips.
 * @property non-empty-list<array{file_path: string, clip_path: string|null}> $photos The photos taken in the session, one per slot in slot order, each with the behind-the-scenes clip recorded during the countdown before it, if the kiosk made one.
 */
#[Fillable(['ref', 'frame_id', 'file_path', 'clip_path', 'photos'])]
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
            && count($this->photos) === count($this->frame->slots)
            && collect($this->photos)->every(fn (array $photo): bool => $photo['clip_path'] !== null);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'photos' => 'array',
        ];
    }
}
