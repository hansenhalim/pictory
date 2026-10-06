<?php

namespace App\Filament\Resources\Frames\Pages;

use App\Filament\Resources\Frames\FrameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFrames extends ListRecords
{
    protected static string $resource = FrameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
