<?php

namespace App\Filament\Resources\Papers;

use App\Filament\Resources\Papers\Pages\ListPapers;
use App\Filament\Resources\Papers\Pages\ViewPaper;
use App\Filament\Resources\Papers\Schemas\PaperInfolist;
use App\Filament\Resources\Papers\Tables\PapersTable;
use App\Models\Paper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Papers are uploaded by the kiosk at the end of a session, so the admin only views and deletes them.
 */
class PaperResource extends Resource
{
    protected static ?string $model = Paper::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    public static function infolist(Schema $schema): Schema
    {
        return PaperInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PapersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPapers::route('/'),
            'view' => ViewPaper::route('/{record}'),
        ];
    }
}
