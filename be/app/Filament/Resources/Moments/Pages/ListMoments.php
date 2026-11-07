<?php

namespace App\Filament\Resources\Moments\Pages;

use App\Filament\Resources\Moments\MomentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMoments extends ListRecords
{
    protected static string $resource = MomentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
