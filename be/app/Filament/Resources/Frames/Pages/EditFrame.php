<?php

namespace App\Filament\Resources\Frames\Pages;

use App\Filament\Resources\Frames\FrameResource;
use App\Filament\Resources\Frames\Schemas\FrameForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditFrame extends EditRecord
{
    protected static string $resource = FrameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FrameResource::replicateAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['slots'] = FrameForm::slotsToFormState($data['slots']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FrameForm::mutateSlotsBeforeSaving($data);
    }
}
