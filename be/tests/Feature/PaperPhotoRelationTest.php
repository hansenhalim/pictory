<?php

use App\Models\Frame;
use App\Models\Paper;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('paper has the photos sharing its ref', function () {
    $paper = Paper::factory()->create();
    Photo::factory()->count(3)->create(['ref' => $paper->ref]);
    Photo::factory()->create();

    expect($paper->photos)->toHaveCount(3)
        ->each(fn ($photo) => $photo->ref->toBe($paper->ref));
});

test('photo belongs to the paper sharing its ref', function () {
    $paper = Paper::factory()->create();
    $photo = Photo::factory()->for($paper)->create();

    expect($photo->ref)->toBe($paper->ref)
        ->and($photo->paper->is($paper))->toBeTrue();
});

test('photo uploaded before its paper has no paper yet', function () {
    $photo = Photo::factory()->create();

    expect($photo->paper)->toBeNull();
});

test('frame slots round trip as nested placements', function () {
    $frame = Frame::factory()->duplicated()->create()->fresh();

    expect($frame->slots)->toHaveCount(3)
        ->and($frame->slots[0])->toHaveCount(2)
        ->and($frame->slots[0][1])->toBe(['x' => 629, 'y' => 40, 'width' => 600, 'height' => 400]);
});

test('frame has no qr codes by default', function () {
    $frame = Frame::create([
        'file_path' => 'frames/frame.png',
        'slots' => [[['x' => 29, 'y' => 40, 'width' => 600, 'height' => 400]]],
    ]);

    expect($frame->qr_codes)->toBe([])
        ->and($frame->fresh()->qr_codes)->toBe([]);
});
