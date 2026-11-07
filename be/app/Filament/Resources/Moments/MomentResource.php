<?php

namespace App\Filament\Resources\Moments;

use App\Filament\Resources\Moments\Pages\CreateMoment;
use App\Filament\Resources\Moments\Pages\EditMoment;
use App\Filament\Resources\Moments\Pages\ListMoments;
use App\Filament\Resources\Moments\RelationManagers\PapersRelationManager;
use App\Filament\Resources\Moments\Schemas\MomentForm;
use App\Filament\Resources\Moments\Tables\MomentsTable;
use App\Models\Moment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * A moment is created with the papers of the frames it was shot with, curated on its edit page,
 * then shared with the client by its link.
 */
class MomentResource extends Resource
{
    protected static ?string $model = Moment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MomentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MomentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PapersRelationManager::class,
        ];
    }

    public static function openAction(): Action
    {
        return Action::make('open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (Moment $record): string => $record->url(), shouldOpenInNewTab: true);
    }

    /**
     * Delete a moment, which stops its link working but leaves its papers in place.
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(fn (Moment $record): string => 'Its link will stop working. '
                .trans_choice('{0} It has no papers.|{1} Its paper stays in Papers.|[2,*] Its :count papers stay in Papers.', $record->papers()->count()));
    }

    /**
     * Tell the admin how many papers were added, and how many were left in other moments.
     *
     * @param  array{added: int, skipped: int}  $result
     */
    public static function papersAddedNotification(array $result): Notification
    {
        return Notification::make()
            ->title('Added '.trans_choice('{0} no papers|{1} 1 paper|[2,*] :count papers', $result['added']))
            ->body($result['skipped'] > 0
                ? 'Skipped '.trans_choice('{1} 1 paper|[2,*] :count papers', $result['skipped']).' already in other moments.'
                : null)
            ->status($result['added'] > 0 ? 'success' : 'warning');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMoments::route('/'),
            'create' => CreateMoment::route('/create'),
            'edit' => EditMoment::route('/{record}/edit'),
        ];
    }
}
