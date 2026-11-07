<?php

namespace App\Filament\Resources\Moments\Tables;

use App\Filament\Resources\Moments\MomentResource;
use App\Models\Moment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MomentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_path')
                    ->label('Cover'),
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('event_date')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('papers_count')
                    ->label('Papers')
                    ->counts('papers'),
                TextColumn::make('link')
                    ->state(fn (Moment $record): string => $record->url())
                    ->formatStateUsing(fn (string $state): string => Str::after($state, '://'))
                    ->copyable()
                    ->copyMessage('Link copied')
                    ->limit(32),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                MomentResource::openAction(),
                EditAction::make(),
                MomentResource::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
