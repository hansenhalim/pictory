<?php

namespace App\Filament\Resources\Papers\Pages;

use App\Filament\Resources\Papers\PaperResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPaper extends ViewRecord
{
    protected static string $resource = PaperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
