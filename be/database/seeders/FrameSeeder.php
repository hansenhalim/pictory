<?php

namespace Database\Seeders;

use App\Models\Frame;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class FrameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        $filePath = 'frames/denny-shierlyn.png';
        $disk->put($filePath, file_get_contents(database_path('seeders/frames/denny-shierlyn.png')));

        Frame::query()->create([
            'file_path' => $filePath,
            'slots' => [
                [['x' => -508, 'y' => 40, 'width' => 2266, 'height' => 1511]],
            ],
            'qr_codes' => [
                ['x' => 1040, 'y' => 1660, 'size' => 120],
            ],
            'cut' => false,
        ]);
    }
}
