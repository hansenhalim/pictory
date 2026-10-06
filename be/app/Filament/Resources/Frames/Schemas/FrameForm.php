<?php

namespace App\Filament\Resources\Frames\Schemas;

use Closure;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JsonException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FrameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 5])
            ->components([
                Group::make([
                    FileUpload::make('file_path')
                        ->label('Frame image')
                        ->image()
                        ->acceptedFileTypes(['image/png'])
                        ->directory('frames')
                        ->required()
                        ->columnSpanFull(),
                    Toggle::make('slots_as_json')
                        ->label('Advanced Mode')
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(fn (bool $state, Get $get, Set $set) => self::switchSlotsMode($state, $get, $set))
                        ->columnSpanFull(),
                    CodeEditor::make('slots_json')
                        ->label('Photo slots')
                        ->helperText('A list of slots, one per photo. Each slot is a list of placements: {"x": 29, "y": 40, "width": 600, "height": 400}.')
                        ->language(Language::Json)
                        ->required()
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if ($error = self::slotsJsonError($value)) {
                                $fail($error);
                            }
                        })
                        ->visible(fn (Get $get): bool => (bool) $get('slots_as_json'))
                        ->columnSpanFull(),
                    Repeater::make('slots')
                        ->label('Photo slots')
                        ->helperText('One slot per photo. Add placements to print the same photo in several places.')
                        ->schema([
                            Repeater::make('placements')
                                ->hiddenLabel()
                                ->table([
                                    TableColumn::make('X')->markAsRequired(),
                                    TableColumn::make('Y')->markAsRequired(),
                                    TableColumn::make('Width')->markAsRequired(),
                                    TableColumn::make('Height')->markAsRequired(),
                                ])
                                ->compact()
                                ->schema([
                                    TextInput::make('x')->integer()->required(),
                                    TextInput::make('y')->integer()->required(),
                                    TextInput::make('width')->integer()->minValue(1)->required(),
                                    TextInput::make('height')->integer()->minValue(1)->required(),
                                ])
                                ->addActionLabel('Add placement')
                                ->minItems(1)
                                ->defaultItems(1),
                        ])
                        ->itemLabel(fn (int $index): string => 'Photo '.($index + 1))
                        ->addActionLabel('Add slot')
                        ->minItems(1)
                        ->required()
                        ->hidden(fn (Get $get): bool => (bool) $get('slots_as_json'))
                        ->columnSpanFull(),
                    Repeater::make('qr_codes')
                        ->label('QR codes')
                        ->helperText('Optional. Add one per printed copy, e.g. one per strip when the print is cut in half.')
                        ->table([
                            TableColumn::make('X')->markAsRequired(),
                            TableColumn::make('Y')->markAsRequired(),
                            TableColumn::make('Size')->markAsRequired(),
                        ])
                        ->compact()
                        ->schema([
                            TextInput::make('x')->integer()->minValue(0)->required(),
                            TextInput::make('y')->integer()->minValue(0)->required(),
                            TextInput::make('size')->integer()->minValue(1)->required(),
                        ])
                        ->addActionLabel('Add QR code')
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])->columnSpan(['lg' => 3]),
                View::make('filament.frames.preview')
                    ->viewData(fn (View $component, Get $get): array => [
                        'imageUrl' => self::previewImageUrl($get('file_path')),
                        'statePath' => $component->getStatePath(),
                    ])
                    ->columnSpan(['lg' => 2]),
            ]);
    }

    /**
     * Get a short-lived URL for the frame image, whether it is a pending upload or already stored.
     */
    public static function previewImageUrl(mixed $state): ?string
    {
        $file = is_array($state) ? Arr::first($state) : $state;

        return rescue(fn (): ?string => match (true) {
            $file instanceof TemporaryUploadedFile => $file->temporaryUrl(),
            is_string($file) && filled($file) => Storage::disk(config('filament.default_filesystem_disk'))
                ->temporaryUrl($file, now()->addMinutes(30)),
            default => null,
        }, report: false);
    }

    /**
     * Copy the slots into whichever editor was just switched on, refusing to leave JSON mode while the JSON is invalid.
     */
    public static function switchSlotsMode(bool $isJson, Get $get, Set $set): void
    {
        if ($isJson) {
            $set('slots_json', json_encode(self::slotsFromFormState($get('slots') ?? []), JSON_PRETTY_PRINT));

            return;
        }

        if ($error = self::slotsJsonError($get('slots_json'))) {
            $set('slots_as_json', true);

            Notification::make()
                ->title('Fix the slots JSON before switching back')
                ->body($error)
                ->danger()
                ->send();

            return;
        }

        $set('slots', self::slotsToFormState(json_decode($get('slots_json'), associative: true)));
    }

    /**
     * Describe the first problem with slots written as raw JSON, or return null when they are valid.
     */
    public static function slotsJsonError(?string $json): ?string
    {
        try {
            $slots = json_decode($json ?? '', associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'The slots must be valid JSON.';
        }

        $validator = Validator::make(['slots' => $slots], [
            'slots' => ['required', 'list'],
            'slots.*' => ['required', 'list'],
            'slots.*.*' => ['required', 'array:x,y,width,height'],
            'slots.*.*.x' => ['required', 'integer:strict'],
            'slots.*.*.y' => ['required', 'integer:strict'],
            'slots.*.*.width' => ['required', 'integer:strict', 'min:1'],
            'slots.*.*.height' => ['required', 'integer:strict', 'min:1'],
        ]);

        return $validator->errors()->first() ?: null;
    }

    /**
     * Turn the submitted form data into the attributes stored on the frame, whichever slots editor was used.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutateSlotsBeforeSaving(array $data): array
    {
        $data['slots'] = array_key_exists('slots_json', $data)
            ? json_decode($data['slots_json'], associative: true)
            : self::slotsFromFormState($data['slots']);

        unset($data['slots_json']);

        return $data;
    }

    /**
     * Key each slot and placement so the slots repeater can edit them.
     *
     * @param  list<list<array{x: int, y: int, width: int, height: int}>>  $slots
     * @return array<string, array{placements: array<string, array{x: int, y: int, width: int, height: int}>}>
     */
    public static function slotsToFormState(array $slots): array
    {
        return collect($slots)
            ->mapWithKeys(fn (array $placements): array => [
                (string) Str::uuid() => [
                    'placements' => collect($placements)
                        ->mapWithKeys(fn (array $placement): array => [(string) Str::uuid() => $placement])
                        ->all(),
                ],
            ])
            ->all();
    }

    /**
     * Unwrap the slots repeater's items back into the stored slots shape.
     *
     * @param  array<array{placements: array<array<string, mixed>>}>  $slots
     * @return list<list<array<string, mixed>>>
     */
    public static function slotsFromFormState(array $slots): array
    {
        return collect($slots)
            ->map(fn (array $slot): array => collect($slot['placements'] ?? [])
                ->map(fn (array $placement): array => array_map(
                    fn (mixed $value): mixed => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $value,
                    $placement,
                ))
                ->values()
                ->all())
            ->values()
            ->all();
    }
}
