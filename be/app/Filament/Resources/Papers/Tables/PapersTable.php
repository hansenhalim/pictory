<?php

namespace App\Filament\Resources\Papers\Tables;

use App\Filament\Resources\QrCards\Tables\QrCardsTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PapersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Paper'),
                TextColumn::make('ref')
                    ->searchable(),
                TextColumn::make('qrCard.label')
                    ->label('Card')
                    ->placeholder('—'),
                TextColumn::make('photos_count')
                    ->label('Photos')
                    ->counts('photos'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('deck')
                    ->options(fn (): array => QrCardsTable::deckOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $query, int|string $deck): Builder => $query->whereRelation('qrCard', 'deck', $deck),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
