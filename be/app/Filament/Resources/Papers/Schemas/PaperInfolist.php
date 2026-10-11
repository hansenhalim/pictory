<?php

namespace App\Filament\Resources\Papers\Schemas;

use App\Models\Paper;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class PaperInfolist
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
                    TextEntry::make('qrCard.label')
                        ->label('Card')
                        ->url(fn (Paper $record): ?string => $record->qrCard ? route('qr-cards.shared', $record->qrCard->uuid) : null, shouldOpenInNewTab: true)
                        ->placeholder('Not assigned to a card.'),
                    ImageEntry::make('photos.file_path')
                        ->label('Photos')
                        ->url(fn (ImageEntry $component, string $state): ?string => $component->getImageUrl($state), shouldOpenInNewTab: true)
                        ->placeholder('No photos from this session.'),
                ])->columnSpan(['lg' => 3]),
                ImageEntry::make('file_path')
                    ->label('Paper')
                    ->imageWidth('100%')
                    ->imageHeight('auto')
                    ->columnSpan(['lg' => 2]),
            ]);
    }
}
