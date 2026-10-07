<?php

namespace App\Filament\Resources\Papers\Pages;

use App\Filament\Resources\Papers\PaperResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Override;

class ListPapers extends ListRecords
{
    protected static string $resource = PaperResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label('Start')
                ->icon(Heroicon::OutlinedPlay)
                ->url(route('kiosk', ['any' => 'settings'])),
        ];
    }
}
