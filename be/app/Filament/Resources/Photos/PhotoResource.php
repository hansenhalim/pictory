<?php

namespace App\Filament\Resources\Photos;

use App\Filament\Resources\Photos\Pages\ListPhotos;
use App\Filament\Resources\Photos\Pages\ViewPhoto;
use App\Filament\Resources\Photos\Schemas\PhotoInfolist;
use App\Filament\Resources\Photos\Tables\PhotosTable;
use App\Models\Photo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Photos are uploaded by the kiosk as they are taken, so the admin only views and deletes them.
 */
class PhotoResource extends Resource
{
    protected static ?string $model = Photo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    public static function infolist(Schema $schema): Schema
    {
        return PhotoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PhotosTable::configure($table);
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
            'index' => ListPhotos::route('/'),
            'view' => ViewPhoto::route('/{record}'),
        ];
    }
}
