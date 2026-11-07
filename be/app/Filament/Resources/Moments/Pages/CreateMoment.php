<?php

namespace App\Filament\Resources\Moments\Pages;

use App\Filament\Resources\Moments\MomentResource;
use App\Models\Moment;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Override;

/**
 * @property Moment $record
 */
class CreateMoment extends CreateRecord
{
    protected static string $resource = MomentResource::class;

    /** @var array{added: int, skipped: int} */
    private array $papersAdded = ['added' => 0, 'skipped' => 0];

    protected function afterCreate(): void
    {
        $frameIds = $this->data['frames'] ?? [];

        if ($frameIds) {
            $this->papersAdded = $this->record->addPapersFromFrames(array_values($frameIds));
        }
    }

    #[Override]
    protected function getCreatedNotification(): ?Notification
    {
        if (! ($this->data['frames'] ?? [])) {
            return parent::getCreatedNotification();
        }

        return MomentResource::papersAddedNotification($this->papersAdded);
    }

    /**
     * Curate the papers next, on the edit page.
     */
    #[Override]
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
