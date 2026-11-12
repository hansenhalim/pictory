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
 * @property int|null $rating The guest's 1 to 5 stars for the photobooth, asked after they download the soft copy.
 * @property string|null $feedback
 * @property string|null $note A guest's message to the moment's hosts, e.g. a wedding wish for the couple, shown with the paper on the moment's pages.
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
     * Get the moment the admin added this paper to, if any.
     *
     * @return BelongsTo<Moment, $this>
     */
    public function moment(): BelongsTo
    {
        return $this->belongsTo(Moment::class);
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

    protected static function booted(): void
    {
        static::deleted(function (self $paper): void {
            // The database has already let go of the paper; the moment may need a new cover.
            $paper->moment?->syncCover();
        });
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
     * Record a guest's feedback, unless the paper has been rated already.
     *
     * Everyone who scans a paper's QR code is asked, but only the first answer counts, so two
     * guests submitting at once cannot overwrite each other.
     *
     * @return bool Whether this feedback was recorded.
     */
    public function rate(int $rating, ?string $comment): bool
    {
        return $this->fillOnce('rating', ['rating' => $rating, 'feedback' => $comment]);
    }

    /**
     * Record a guest's note, unless the paper has one already.
     *
     * A paper is often a group photo whose QR code several guests scan, so the first note written
     * stands for the group and is never overwritten.
     *
     * @return bool Whether this note was recorded.
     */
    public function leaveNote(string $note): bool
    {
        return $this->fillOnce('note', ['note' => $note]);
    }

    /**
     * Set the attributes in one query that only matches while `$column` is still empty, so of two
     * guests submitting at once, only the first is recorded.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fillOnce(string $column, array $attributes): bool
    {
        $isRecorded = self::query()
            ->whereKey($this->getKey())
            ->whereNull($column)
            ->update($attributes) === 1;

        if ($isRecorded) {
            $this->forceFill($attributes)->syncOriginal();
        } else {
            $this->refresh();
        }

        return $isRecorded;
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
