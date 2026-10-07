<?php

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Resources\Photos\PhotoResource;
use Filament\Resources\Pages\ListRecords;

class ListPhotos extends ListRecords
{
    protected static string $resource = PhotoResource::class;
}
