<?php

namespace App\Filament\Resources\QrCards\Pages;

use App\Filament\Resources\QrCards\QrCardResource;
use App\Filament\Resources\QrCards\Tables\QrCardsTable;
use App\Models\QrCard;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Override;

class ListQrCards extends ListRecords
{
    protected static string $resource = QrCardResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        $decks = QrCardsTable::deckOptions();

        return [
            ActionGroup::make(collect($decks)
                ->map(fn (string $label, int $deck): Action => Action::make("printDeck{$deck}")
                    ->label($label)
                    ->url(route('qr-cards.print', $deck), shouldOpenInNewTab: true))
                ->values()
                ->all())
                ->label('Print deck')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->button()
                ->visible($decks !== []),
            Action::make('generateDeck')
                ->label('New deck')
                ->icon(Heroicon::OutlinedPlus)
                ->requiresConfirmation()
                ->modalDescription('This generates the next deck of '.QrCard::DECK_SIZE.' cards. Decks cannot be deleted.')
                ->action(function (): void {
                    $deck = QrCard::generateDeck();

                    Notification::make()
                        ->title("Deck {$deck} generated")
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
