<?php

namespace App\Models;

use App\Jobs\RenderMomentCover;
use Database\Factories\MomentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A client's gig, e.g. "Rina & Dimas Wedding": the papers shot there, curated by the admin and
 * shared with the client through one link.
 *
 * The link is the only access. It reads like "/m/rina-dimas-wedding-7kq2x9", but only the token
 * at its end identifies the moment; the name in front is decoration, so a link with a typo or an
 * old name still finds it and is redirected to the current one. The token never changes, so a
 * link once sent works for as long as the moment exists.
 *
 * Every paper belongs to at most one moment, and deleting a moment leaves its papers in place.
 *
 * @property Carbon|null $event_date
 * @property 'dark'|'light' $theme How the client's pages look. The link preview is dark either way.
 * @property string|null $cover_path The link preview image, rendered from the cover paper.
 * @property-read string $slug
 */
#[Fillable(['name', 'event_date', 'theme', 'cover_paper_id'])]
class Moment extends Model
{
    /** @use HasFactory<MomentFactory> */
    use HasFactory;

    public const int TOKEN_LENGTH = 6;

    public const array THEMES = ['dark' => 'Dark', 'light' => 'Light'];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'theme' => 'dark',
    ];

    /**
     * Find the moment a slug links to by the token at its end, forgiving the letters Crockford's
     * base32 reads as digits.
     */
    public static function findBySlug(string $slug): ?self
    {
        $token = strtr(Str::lower(Str::afterLast($slug, '-')), 'ilo', '110');

        if (strlen($token) !== self::TOKEN_LENGTH) {
            return null;
        }

        return self::query()->where('token', $token)->first();
    }

    public static function generateToken(): string
    {
        $alphabet = Str::lower(ShortCode::ALPHABET);

        do {
            $token = collect(range(1, self::TOKEN_LENGTH))
                ->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    /**
     * Where the link preview rendered from a paper is stored. Papers never change, so neither
     * does the cover rendered from one.
     */
    public static function coverPathFor(Paper $paper): string
    {
        return "moment-covers/{$paper->ref}.jpg";
    }

    protected static function booted(): void
    {
        static::creating(function (self $moment): void {
            $moment->token ??= self::generateToken();
        });

        static::saved(function (self $moment): void {
            if ($moment->wasChanged('cover_paper_id')) {
                $moment->syncCover();
            }
        });

        static::deleted(function (self $moment): void {
            if ($moment->cover_path) {
                Storage::disk(config('filament.default_filesystem_disk'))->delete($moment->cover_path);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    /**
     * The name, then the token that identifies the moment.
     *
     * @return Attribute<string, never>
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => Str::of($this->name)->slug()->append('-', $this->token)->ltrim('-')->toString());
    }

    public function url(): string
    {
        return route('moments.shared', $this->slug);
    }

    /**
     * Get the papers in this moment.
     *
     * @return HasMany<Paper, $this>
     */
    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class);
    }

    /**
     * Get the paper the admin chose for the link preview, if any.
     *
     * @return BelongsTo<Paper, $this>
     */
    public function coverPaper(): BelongsTo
    {
        return $this->belongsTo(Paper::class, 'cover_paper_id');
    }

    /**
     * Get the paper the link preview shows: the one the admin chose, or else the first one.
     */
    public function resolveCoverPaper(): ?Paper
    {
        return ($this->cover_paper_id ? $this->papers()->whereKey($this->cover_paper_id)->first() : null)
            ?? $this->papers()->oldest()->oldest('id')->first();
    }

    /**
     * Add papers that are in no moment yet. Papers in another moment are left there.
     *
     * @param  Builder<Paper>  $papers
     * @return array{added: int, skipped: int} How many papers were added, and how many were skipped for being in another moment.
     */
    public function addPapers(Builder $papers): array
    {
        $added = (clone $papers)->whereNull('moment_id')->update(['moment_id' => $this->id]);
        $skipped = (clone $papers)->whereNotNull('moment_id')->whereNot('moment_id', $this->id)->count();

        $this->syncCover();

        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * @param  list<int|string>  $frameIds
     * @return array{added: int, skipped: int}
     */
    public function addPapersFromFrames(array $frameIds): array
    {
        return $this->addPapers(Paper::query()->whereIn('frame_id', $frameIds));
    }

    /**
     * @param  list<int|string>  $paperIds
     */
    public function removePapers(array $paperIds): void
    {
        $this->papers()->whereKey($paperIds)->update(['moment_id' => null]);

        if (in_array($this->cover_paper_id, array_map(intval(...), $paperIds), true)) {
            $this->updateQuietly(['cover_paper_id' => null]);
        }

        $this->syncCover();
    }

    /**
     * Render the link preview again if the paper it shows has changed.
     */
    public function syncCover(): void
    {
        $coverPaper = $this->resolveCoverPaper();

        if ($this->cover_path === ($coverPaper ? self::coverPathFor($coverPaper) : null)) {
            return;
        }

        // A block, not an arrow function: a pending dispatch is only sent once it is
        // destructed, which has to happen inside `rescue`.
        rescue(function (): void {
            RenderMomentCover::dispatch($this);
        });
    }
}
