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
 * @property string|null $feedback A guest's rating of the photobooth for staff, asked after they download the soft copy, as `FEEDBACK_TEMPLATE` fills it.
 * @property string|null $note A guest's message to the moment's hosts, e.g. a wedding wish for the couple, signed as `NOTE_TEMPLATE` fills it and shown on the moment's pages.
 */
#[Fillable(['ref', 'frame_id', 'file_path', 'clip_path', 'photos'])]
class Paper extends Model
{
    /** @use HasFactory<PaperFactory> */
    use HasFactory;

    /**
     * How feedback is stored: read by staff only, so the rating, comment and time share one text.
     */
    public const string FEEDBACK_TEMPLATE = "Rating: {rating}/5\nComment: {comment}\nCreated at: {createdAt}";

    /**
     * How a note is stored and shown: the message, then who sent it.
     */
    public const string NOTE_TEMPLATE = "{note}\n\n— {sender}";

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
        return $this->fillOnce('feedback', strtr(self::FEEDBACK_TEMPLATE, [
            '{rating}' => $rating,
            '{comment}' => $comment ?? '—',
            '{createdAt}' => now()->toIso8601String(),
        ]));
    }

    /**
     * Record a guest's note, unless the paper has one already.
     *
     * A paper is often a group photo whose QR code several guests scan, so the first note written
     * stands for the group and is never overwritten.
     *
     * @return bool Whether this note was recorded.
     */
    public function leaveNote(string $note, string $sender): bool
    {
        return $this->fillOnce('note', strtr(self::NOTE_TEMPLATE, [
            '{note}' => $note,
            '{sender}' => $sender,
        ]));
    }

    /**
     * Set the column in one query that only matches while it is still empty, so of two guests
     * submitting at once, only the first is recorded.
     */
    private function fillOnce(string $column, string $value): bool
    {
        $isRecorded = self::query()
            ->whereKey($this->getKey())
            ->whereNull($column)
            ->update([$column => $value]) === 1;

        if ($isRecorded) {
            $this->forceFill([$column => $value])->syncOriginal();
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
