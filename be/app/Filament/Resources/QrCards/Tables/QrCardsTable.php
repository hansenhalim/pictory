<?php

namespace App\Filament\Resources\QrCards\Tables;

use App\Models\QrCard;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QrCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Card')
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search)),
                TextColumn::make('papers_count')
                    ->label('Sessions')
                    ->counts('papers'),
                TextColumn::make('papers_max_created_at')
                    ->label('Last session')
                    ->max('papers', 'created_at')
                    ->dateTime()
                    ->placeholder('Not used yet'),
            ])
            ->groups([
                Group::make('deck')
                    ->getTitleFromRecordUsing(fn (QrCard $record): string => "Deck {$record->deck}")
                    ->titlePrefixedWithLabel(false)
                    ->getDescriptionFromRecordUsing(fn (QrCard $record): string => self::usage($record->deck)),
            ])
            ->defaultGroup('deck', 'desc')
            ->groupingSettingsHidden()
            ->defaultSort('number')
            ->paginated([20, 100])
            ->defaultPaginationPageOption(100)
            ->filters([
                SelectFilter::make('deck')
                    ->options(fn (): array => self::deckOptions()),
            ])
            ->recordActions([
                Action::make('open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (QrCard $record): string => route('qr-cards.shared', $record->uuid), shouldOpenInNewTab: true),
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function deckOptions(): array
    {
        return QrCard::query()
            ->distinct()
            ->orderByDesc('deck')
            ->pluck('deck')
            ->mapWithKeys(fn (int $deck): array => [$deck => "Deck {$deck}"])
            ->all();
    }

    /**
     * Find a card by its label, e.g. "3.42", or a whole deck by its number.
     *
     * @param  Builder<QrCard>  $query
     * @return Builder<QrCard>
     */
    private static function search(Builder $query, string $search): Builder
    {
        if (preg_match('/^(\d{1,9})(?:\.(\d{1,3}))?$/', trim($search), $matches) !== 1) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('deck', (int) $matches[1])
            ->when(isset($matches[2]), fn (Builder $query): Builder => $query->where('number', (int) $matches[2]));
    }

    private static function usage(int $deck): string
    {
        $usedCount = QrCard::query()->where('deck', $deck)->has('papers')->count();

        return "{$usedCount} of ".QrCard::DECK_SIZE.' cards used';
    }
}
