<?php

namespace App\Filament\Resources\QrCards;

use App\Filament\Resources\QrCards\Pages\ListQrCards;
use App\Filament\Resources\QrCards\Tables\QrCardsTable;
use App\Models\QrCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Cards are generated a deck at a time and assigned to sessions by the kiosk, so the admin only
 * generates, prints and browses them. Decks are never deleted, since their cards are out in
 * customers' hands.
 */
class QrCardResource extends Resource
{
    protected static ?string $model = QrCard::class;

    protected static ?string $modelLabel = 'QR card';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $recordTitleAttribute = 'label';

    public static function table(Table $table): Table
    {
        return QrCardsTable::configure($table);
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
            'index' => ListQrCards::route('/'),
        ];
    }
}
