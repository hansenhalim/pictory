<?php

namespace App\Models;

use Database\Factories\PaperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['file_path', 'ref'])]
class Paper extends Model
{
    /** @use HasFactory<PaperFactory> */
    use HasFactory;

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
     * Get the short code printed on this paper, if it had one.
     *
     * @return HasOne<ShortCode, $this>
     */
    public function shortCode(): HasOne
    {
        return $this->hasOne(ShortCode::class);
    }
}
