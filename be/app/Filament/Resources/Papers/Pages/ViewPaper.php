<?php

namespace App\Filament\Resources\Papers\Pages;

use App\Filament\Resources\Papers\PaperResource;
use App\Jobs\RenderClipPaper;
use App\Models\Paper;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPaper extends ViewRecord
{
    protected static string $resource = PaperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('renderClipPaper')
                ->label('Render clip paper')
                ->color('gray')
                ->visible(fn (Paper $record): bool => $record->canRenderClipPaper())
                ->action(function (Paper $record): void {
                    RenderClipPaper::dispatch($record);

                    Notification::make()
                        ->title('Rendering the clip paper')
                        ->body('Reload this page in a minute to see it.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
