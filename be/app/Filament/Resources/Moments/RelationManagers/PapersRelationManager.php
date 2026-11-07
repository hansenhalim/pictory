<?php

namespace App\Filament\Resources\Moments\RelationManagers;

use App\Filament\Resources\Moments\MomentResource;
use App\Filament\Resources\Moments\Schemas\MomentForm;
use App\Filament\Resources\Moments\Tables\AddablePapersTable;
use App\Filament\Resources\Papers\PaperResource;
use App\Models\Moment;
use App\Models\Paper;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TableSelect;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Curate a moment: its papers in the order the client sees them, oldest first.
 *
 * @property Moment $ownerRecord
 */
class PapersRelationManager extends RelationManager
{
    protected static string $relationship = 'papers';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('qrCard'))
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Paper')
                    ->imageHeight('6rem'),
                TextColumn::make('number')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('frame_id')
                    ->label('Frame')
                    ->formatStateUsing(fn (int $state): string => "Frame {$state}")
                    ->placeholder('—'),
                TextColumn::make('qrCard.label')
                    ->label('Card')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at')
            ->headerActions([
                Action::make('addPapers')
                    ->label('Add papers')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalWidth('5xl')
                    ->schema([
                        TableSelect::make('papers')
                            ->hiddenLabel()
                            ->tableConfiguration(AddablePapersTable::class)
                            ->multiple()
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->notifyAdded(
                        $this->ownerRecord->addPapers(Paper::query()->whereKey($data['papers'])),
                    )),
                Action::make('addPapersFromFrames')
                    ->label('Add papers from frames')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->color('gray')
                    ->schema([
                        MomentForm::framesSelect('frames')
                            ->helperText('Papers removed earlier are added back. Papers in other moments are skipped.')
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->notifyAdded(
                        $this->ownerRecord->addPapersFromFrames(array_values($data['frames'])),
                    )),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Paper $record): string => PaperResource::getUrl('view', ['record' => $record]), shouldOpenInNewTab: true),
                Action::make('remove')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->color('danger')
                    ->action(fn (Paper $record) => $this->ownerRecord->removePapers([$record->id])),
            ])
            ->toolbarActions([
                BulkAction::make('remove')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The papers stay in Papers, and can be added back.')
                    ->action(fn (Collection $records) => $this->ownerRecord->removePapers($records->modelKeys()))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * @param  array{added: int, skipped: int}  $result
     */
    private function notifyAdded(array $result): void
    {
        MomentResource::papersAddedNotification($result)->send();
    }
}
