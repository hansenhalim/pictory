<?php

namespace App\Filament\Resources\Frames;

use App\Filament\Resources\Frames\Pages\CreateFrame;
use App\Filament\Resources\Frames\Pages\EditFrame;
use App\Filament\Resources\Frames\Pages\ListFrames;
use App\Filament\Resources\Frames\Schemas\FrameForm;
use App\Filament\Resources\Frames\Tables\FramesTable;
use App\Models\Frame;
use BackedEnum;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrameResource extends Resource
{
    protected static ?string $model = Frame::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FrameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FramesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Copy a frame, giving the copy its own image file, then open the copy for editing.
     */
    public static function replicateAction(): ReplicateAction
    {
        return ReplicateAction::make()
            ->beforeReplicaSaved(function (Frame $replica): void {
                $disk = Storage::disk(config('filament.default_filesystem_disk'));
                $copyPath = 'frames/'.Str::random(40).'.'.pathinfo($replica->file_path, PATHINFO_EXTENSION);

                if ($disk->copy($replica->file_path, $copyPath)) {
                    $replica->file_path = $copyPath;
                }
            })
            ->successRedirectUrl(fn (Frame $replica): string => static::getUrl('edit', ['record' => $replica]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFrames::route('/'),
            'create' => CreateFrame::route('/create'),
            'edit' => EditFrame::route('/{record}/edit'),
        ];
    }
}
