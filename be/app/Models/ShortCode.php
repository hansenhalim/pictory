<?php

namespace App\Models;

use Database\Factories\ShortCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * A short code printed as a QR code on a paper, linking to its soft copy.
 *
 * Kiosks reserve codes ahead of time so they can print offline. A code is reserved while
 * it has no paper, and used once the paper it was printed on is uploaded.
 */
#[Fillable(['code', 'paper_id'])]
class ShortCode extends Model
{
    /** @use HasFactory<ShortCodeFactory> */
    use HasFactory;

    /**
     * Crockford's base32 alphabet, which leaves out I, L, O and U to avoid misreading.
     */
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const int LENGTH = 6;

    /**
     * Reserve fresh codes for a kiosk to print with.
     *
     * @return Collection<int, self>
     */
    public static function reserve(int $count): Collection
    {
        return Collection::times($count, fn (): self => retry(
            5,
            fn (): self => self::query()->create(['code' => self::generateCode()]),
            when: fn (Throwable $exception): bool => $exception instanceof UniqueConstraintViolationException,
        ));
    }

    public static function generateCode(): string
    {
        return collect(range(1, self::LENGTH))
            ->map(fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
            ->implode('');
    }

    /**
     * Get the paper this code was printed on, if it has been uploaded.
     *
     * @return BelongsTo<Paper, $this>
     */
    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class);
    }
}
