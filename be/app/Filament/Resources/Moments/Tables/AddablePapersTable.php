<?php

namespace App\Filament\Resources\Moments\Tables;

use App\Models\Frame;
use App\Models\Paper;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The papers an admin can add to a moment: those in no moment yet.
 */
class AddablePapersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(Paper::query()->whereNull('moment_id')->with('qrCard'))
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Paper')
                    ->imageHeight('6rem'),
                TextColumn::make('frame_id')
                    ->label('Frame')
                    ->formatStateUsing(fn (int $state): string => "Frame {$state}")
                    ->placeholder('—'),
                TextColumn::make('qrCard.label')
                    ->label('Card')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('frame_id')
                    ->label('Frame')
                    ->options(fn (): array => Frame::query()
                        ->latest()
                        ->pluck('id')
                        ->mapWithKeys(fn (int $id): array => [$id => "Frame {$id}"])
                        ->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ]);
    }
}
