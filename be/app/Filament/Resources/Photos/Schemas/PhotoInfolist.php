<?php

namespace App\Filament\Resources\Photos\Schemas;

use App\Filament\Resources\Papers\PaperResource;
use App\Models\Photo;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class PhotoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 5])
            ->components([
                Group::make([
                    TextEntry::make('ref')
                        ->copyable(),
                    TextEntry::make('created_at')
                        ->dateTime(),
                    ImageEntry::make('paper.file_path')
                        ->label('Paper')
                        ->url(fn (Photo $record): ?string => $record->paper
                            ? PaperResource::getUrl('view', ['record' => $record->paper])
                            : null)
                        ->placeholder('No paper from this session yet.'),
                ])->columnSpan(['lg' => 3]),
                ImageEntry::make('file_path')
                    ->label('Photo')
                    ->imageWidth('100%')
                    ->imageHeight('auto')
                    ->columnSpan(['lg' => 2]),
            ]);
    }
}
