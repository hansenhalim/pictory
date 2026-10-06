<?php

namespace App\Filament\Resources\Frames\Pages;

use App\Filament\Resources\Frames\FrameResource;
use App\Filament\Resources\Frames\Schemas\FrameForm;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateFrame extends CreateRecord
{
    protected static string $resource = FrameResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return FrameForm::mutateSlotsBeforeSaving($data);
    }
}
