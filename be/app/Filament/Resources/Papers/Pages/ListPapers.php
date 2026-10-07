<?php

namespace App\Filament\Resources\Papers\Pages;

use App\Filament\Resources\Papers\PaperResource;
use Filament\Resources\Pages\ListRecords;

class ListPapers extends ListRecords
{
    protected static string $resource = PaperResource::class;
}
