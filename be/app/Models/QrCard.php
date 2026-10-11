<?php

namespace App\Models;

use Database\Factories\QrCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Throwable;

/**
 * A pre-printed card whose QR code links to the soft copies of every session assigned to it.
 *
 * Cards are printed in decks of 100 and kept by customers. The operator assigns a session to a
 * card at the kiosk by typing its label, e.g. "3.42" for card 42 of deck 3, so a customer with
 * several sessions finds all of them behind one card.
 *
 * @property-read string $label
 */
#[Fillable(['deck', 'number', 'uuid'])]
class QrCard extends Model
{
    /** @use HasFactory<QrCardFactory> */
    use HasFactory;

    public const int DECK_SIZE = 100;

    /**
     * A label as the kiosk sends it: no leading zeros, and short enough to fit the columns.
     */
    public const string LABEL_PATTERN = '/^([1-9]\d{0,8})\.([1-9]\d{0,2})$/';

    /**
     * Generate the cards of the next deck.
     *
     * @return int The new deck's number.
     */
    public static function generateDeck(): int
    {
        return retry(
            3,
            function (): int {
                $deck = (self::query()->max('deck') ?? 0) + 1;
                $now = now();

                self::query()->insert(collect(range(1, self::DECK_SIZE))
                    ->map(fn (int $number): array => [
                        'deck' => $deck,
                        'number' => $number,
                        'uuid' => (string) Str::uuid(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all());

                return $deck;
            },
            when: fn (Throwable $exception): bool => $exception instanceof UniqueConstraintViolationException,
        );
    }

    public static function findByLabel(string $label): ?self
    {
        if (! preg_match(self::LABEL_PATTERN, $label, $matches)) {
            return null;
        }

        return self::query()
            ->where('deck', (int) $matches[1])
            ->where('number', (int) $matches[2])
            ->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deck' => 'integer',
            'number' => 'integer',
        ];
    }

    /**
     * What the operator types at the kiosk and what is printed on the card.
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->deck}.{$this->number}");
    }

    /**
     * The URL printed as the card's QR code. It is uppercase so the whole URL fits QR
     * alphanumeric mode, which keeps the code small; both cases are routed.
     */
    public function qrCodeUrl(): string
    {
        return Str::upper(route('qr-cards.shared', $this->uuid));
    }

    /**
     * Get the papers of the sessions assigned to this card.
     *
     * @return HasMany<Paper, $this>
     */
    public function papers(): HasMany
    {
        return $this->hasMany(Paper::class);
    }
}
