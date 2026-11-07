<?php

namespace App\Filament\Resources\Moments\Schemas;

use App\Models\Frame;
use App\Models\Moment;
use App\Models\Paper;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class MomentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Rina & Dimas Wedding'),
                DatePicker::make('event_date'),
                ToggleButtons::make('theme')
                    ->options(Moment::THEMES)
                    ->icons(['dark' => Heroicon::OutlinedMoon, 'light' => Heroicon::OutlinedSun])
                    ->default('dark')
                    ->required()
                    ->inline(),
                self::framesSelect('frames')
                    ->helperText('Every paper shot with these frames is added, except those already in other moments.')
                    ->dehydrated(false)
                    ->visibleOn('create'),
                Select::make('cover_paper_id')
                    ->label('Cover paper')
                    ->helperText('Shown when the link is shared, e.g. on WhatsApp.')
                    ->placeholder('First paper')
                    ->options(fn (Moment $record): array => self::coverOptions($record))
                    ->allowHtml()
                    ->native(false)
                    ->visibleOn('edit'),
                TextEntry::make('link')
                    ->state(fn (Moment $record): string => $record->url())
                    ->url(fn (Moment $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->copyable()
                    ->copyMessage('Link copied')
                    ->icon(Heroicon::OutlinedLink)
                    ->visibleOn('edit'),
            ]);
    }

    /**
     * A select of frames, each shown by its image and how many papers were shot with it.
     */
    public static function framesSelect(string $name): Select
    {
        return Select::make($name)
            ->multiple()
            ->options(fn (): array => self::frameOptions())
            ->allowHtml()
            ->searchable(false);
    }

    /**
     * @return array<int, string>
     */
    private static function frameOptions(): array
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        return Frame::query()
            ->withCount('papers')
            ->latest()
            ->get()
            ->mapWithKeys(fn (Frame $frame): array => [
                $frame->id => self::thumbnailLabel(
                    $disk->temporaryUrl($frame->file_path, now()->addHour()),
                    "Frame {$frame->id} · ".trans_choice('{0} no papers|{1} 1 paper|[2,*] :count papers', $frame->papers_count)
                        .' · '.$frame->created_at->format('j M Y'),
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function coverOptions(Moment $moment): array
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        return $moment->papers()
            ->oldest()
            ->oldest('id')
            ->get()
            ->values()
            ->mapWithKeys(fn (Paper $paper, int $index): array => [
                $paper->id => self::thumbnailLabel(
                    $disk->temporaryUrl($paper->file_path, now()->addHour()),
                    str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).' · '.$paper->created_at->format('j M Y H:i'),
                ),
            ])
            ->all();
    }

    private static function thumbnailLabel(string $imageUrl, string $text): string
    {
        return '<span style="display:flex;align-items:center;gap:.75rem">'
            .'<img src="'.e($imageUrl).'" alt="" style="height:3rem;width:auto;border-radius:.25rem">'
            .'<span>'.e($text).'</span>'
            .'</span>';
    }
}
