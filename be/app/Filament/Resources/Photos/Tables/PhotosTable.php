<?php

namespace App\Filament\Resources\Photos\Tables;

use App\Filament\Resources\Papers\PaperResource;
use App\Models\Photo;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PhotosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Photo'),
                TextColumn::make('ref')
                    ->searchable(),
                ImageColumn::make('paper.file_path')
                    ->label('Paper')
                    ->url(fn (Photo $record): ?string => $record->paper
                        ? PaperResource::getUrl('view', ['record' => $record->paper])
                        : null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
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
