<?php

namespace App\Filament\Resources\Moments\Pages;

use App\Filament\Resources\Moments\MomentResource;
use Filament\Resources\Pages\EditRecord;

class EditMoment extends EditRecord
{
    protected static string $resource = MomentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MomentResource::openAction(),
            MomentResource::deleteAction(),
        ];
    }
}
