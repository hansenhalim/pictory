<?php

use App\Filament\Resources\Frames\FrameResource;
use App\Filament\Resources\Frames\Pages\CreateFrame;
use App\Filament\Resources\Frames\Pages\EditFrame;
use App\Filament\Resources\Frames\Pages\ListFrames;
use App\Filament\Resources\Frames\Schemas\FrameForm;
use App\Models\Frame;
use App\Models\User;
use Filament\Actions\ReplicateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->undoRepeaterFake = Repeater::fake();
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    ($this->undoRepeaterFake)();
});

test('frames are listed', function () {
    $frames = Frame::factory()->count(2)->create();

    Livewire::test(ListFrames::class)
        ->assertOk()
        ->assertCanSeeTableRecords($frames);
});

test('new frame starts with one slot ready for a placement', function () {
    Livewire::test(CreateFrame::class)
        ->assertSet('data.slots', fn (array $slots): bool => count($slots) === 1)
        ->assertSet('data.slots.0.placements', fn (?array $placements): bool => count($placements ?? []) === 1)
        ->assertSee('Photo 1');
});

test('frame is uploaded with nested slots', function () {
    Livewire::test(CreateFrame::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->image('frame.png', 1200, 1800),
            'slots' => [
                ['placements' => [
                    ['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400],
                    ['x' => 629, 'y' => 40, 'width' => 600, 'height' => 400],
                ]],
                ['placements' => [
                    ['x' => 29, 'y' => 440, 'width' => 600, 'height' => 400],
                ]],
            ],
            'qr_codes' => [
                ['x' => 269, 'y' => 1280, 'size' => 120],
                ['x' => 869, 'y' => 1280, 'size' => 120],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $frame = Frame::sole();

    expect($frame->file_path)->toStartWith('frames/')
        ->and($frame->slots)->toBe([
            [
                ['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400],
                ['x' => 629, 'y' => 40, 'width' => 600, 'height' => 400],
            ],
            [
                ['x' => 29, 'y' => 440, 'width' => 600, 'height' => 400],
            ],
        ])
        ->and($frame->qr_codes)->toBe([
            ['x' => 269, 'y' => 1280, 'size' => 120],
            ['x' => 869, 'y' => 1280, 'size' => 120],
        ]);

    Storage::disk('local')->assertExists($frame->file_path);
});

test('frame without qr codes is saved with an empty list', function () {
    Livewire::test(CreateFrame::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->image('frame.png'),
            'slots' => [
                ['placements' => [
                    ['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400],
                ]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Frame::sole()->qr_codes)->toBe([]);
});

test('slot placements may start outside the image', function () {
    Livewire::test(CreateFrame::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->image('frame.png'),
            'slots' => [
                ['placements' => [
                    ['x' => -20, 'y' => -15, 'width' => 640, 'height' => 430],
                ]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Frame::sole()->slots)->toBe([
        [['x' => -20, 'y' => -15, 'width' => 640, 'height' => 430]],
    ]);
});

test('qr code size must be positive', function () {
    Livewire::test(CreateFrame::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->image('frame.png'),
            'slots' => [
                ['placements' => [
                    ['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400],
                ]],
            ],
            'qr_codes' => [
                ['x' => 269, 'y' => 1280, 'size' => 0],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['qr_codes.0.size' => 'min']);

    expect(Frame::count())->toBe(0);
});

test('frame requires an image and at least one slot', function () {
    Livewire::test(CreateFrame::class)
        ->fillForm([
            'file_path' => null,
            'slots' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['file_path' => 'required', 'slots']);

    expect(Frame::count())->toBe(0);
});

test('frame image must be a png', function () {
    Livewire::test(CreateFrame::class)
        ->fillForm([
            'file_path' => UploadedFile::fake()->image('frame.jpg'),
            'slots' => [
                ['placements' => [
                    ['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400],
                ]],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['file_path']);

    expect(Frame::count())->toBe(0);
});

test('frame slots and qr codes are editable', function () {
    $frame = Frame::factory()->duplicated()->withQrCodes()->create([
        'file_path' => UploadedFile::fake()->image('frame.png')->store('frames'),
    ]);

    Livewire::test(EditFrame::class, ['record' => $frame->getRouteKey()])
        ->assertSet('data.slots.0.placements.1', ['x' => 629, 'y' => 40, 'width' => 600, 'height' => 400])
        ->fillForm([
            'slots' => [
                ['placements' => [
                    ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1800],
                ]],
            ],
            'qr_codes' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $frame->refresh();

    expect($frame->slots)->toBe([
        [['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 1800]],
    ])->and($frame->qr_codes)->toBe([]);
});

test('preview asks for a frame image until one is uploaded', function () {
    Livewire::test(CreateFrame::class)
        ->assertSee('Upload a frame image to see the preview.')
        ->assertDontSeeHtml('alt="Frame preview"')
        ->fillForm(['file_path' => UploadedFile::fake()->image('frame.png')])
        ->assertDontSee('Upload a frame image to see the preview.')
        ->assertSeeHtml('alt="Frame preview"');
});

test('preview shows the stored frame image when editing', function () {
    $frame = Frame::factory()->create([
        'file_path' => UploadedFile::fake()->image('frame.png')->store('frames'),
    ]);

    Livewire::test(EditFrame::class, ['record' => $frame->getRouteKey()])
        ->assertDontSee('Upload a frame image to see the preview.')
        ->assertSeeHtml('alt="Frame preview"');
});

test('switching to json mode copies the current slots', function () {
    $frame = Frame::factory()->duplicated()->create([
        'file_path' => UploadedFile::fake()->image('frame.png')->store('frames'),
    ]);

    Livewire::test(EditFrame::class, ['record' => $frame->getRouteKey()])
        ->set('data.slots_as_json', true)
        ->assertSet('data.slots_json', fn (string $json): bool => json_decode($json, associative: true) === $frame->slots);
});

test('frame is saved from json mode', function () {
    $slots = [
        [['x' => -20, 'y' => 40, 'width' => 600, 'height' => 400], ['x' => 629, 'y' => 40, 'width' => 600, 'height' => 400]],
        [['x' => 29, 'y' => 440, 'width' => 600, 'height' => 400]],
    ];

    Livewire::test(CreateFrame::class)
        ->set('data.slots_as_json', true)
        ->fillForm([
            'file_path' => UploadedFile::fake()->image('frame.png'),
            'slots_json' => json_encode($slots),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Frame::sole()->slots)->toBe($slots);
});

test('json slots must match the slot shape', function (string $json) {
    Livewire::test(CreateFrame::class)
        ->set('data.slots_as_json', true)
        ->fillForm([
            'file_path' => UploadedFile::fake()->image('frame.png'),
            'slots_json' => $json,
        ])
        ->call('create')
        ->assertHasFormErrors(['slots_json']);

    expect(Frame::count())->toBe(0);
})->with([
    'invalid json' => '[[{"x": 29,',
    'no slots' => '[]',
    'slot without placements' => '[[]]',
    'placement missing height' => '[[{"x": 29, "y": 40, "width": 600}]]',
    'text instead of number' => '[[{"x": "29", "y": 40, "width": 600, "height": 400}]]',
    'zero width' => '[[{"x": 29, "y": 40, "width": 0, "height": 400}]]',
    'unknown key' => '[[{"x": 29, "y": 40, "width": 600, "height": 400, "z": 1}]]',
]);

test('switching back to tables loads the json slots', function () {
    Livewire::test(CreateFrame::class)
        ->set('data.slots_as_json', true)
        ->set('data.slots_json', '[[{"x": 1, "y": 2, "width": 3, "height": 4}], [{"x": 5, "y": 6, "width": 7, "height": 8}]]')
        ->set('data.slots_as_json', false)
        ->assertSet('data.slots', fn (array $slots): bool => FrameForm::slotsFromFormState($slots) === [
            [['x' => 1, 'y' => 2, 'width' => 3, 'height' => 4]],
            [['x' => 5, 'y' => 6, 'width' => 7, 'height' => 8]],
        ]);
});

test('switching back to tables is refused while the json is invalid', function () {
    Livewire::test(CreateFrame::class)
        ->set('data.slots_as_json', true)
        ->set('data.slots_json', '[[{"x": 1}]]')
        ->set('data.slots_as_json', false)
        ->assertSet('data.slots_as_json', true)
        ->assertNotified('Fix the slots JSON before switching back');
});

test('frame is replicated from the table with its own copy of the image', function () {
    $frame = Frame::factory()->duplicated()->withQrCodes()->create([
        'file_path' => UploadedFile::fake()->image('frame.png')->store('frames'),
    ]);

    Livewire::test(ListFrames::class)
        ->callAction(TestAction::make('replicate')->table($frame));

    $replica = Frame::whereKeyNot($frame->getKey())->sole();

    expect($replica->slots)->toBe($frame->slots)
        ->and($replica->qr_codes)->toBe($frame->qr_codes)
        ->and($replica->file_path)->not->toBe($frame->file_path)->toStartWith('frames/')->toEndWith('.png');

    Storage::disk('local')->assertExists([$frame->file_path, $replica->file_path]);
});

test('frame is replicated from its edit page and the copy opens for editing', function () {
    $frame = Frame::factory()->create([
        'file_path' => UploadedFile::fake()->image('frame.png')->store('frames'),
    ]);

    $component = Livewire::test(EditFrame::class, ['record' => $frame->getRouteKey()])
        ->callAction(ReplicateAction::class);

    $replica = Frame::whereKeyNot($frame->getKey())->sole();

    $component->assertRedirect(FrameResource::getUrl('edit', ['record' => $replica]));
});
