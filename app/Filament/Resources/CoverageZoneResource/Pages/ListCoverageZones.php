<?php

namespace App\Filament\Resources\CoverageZoneResource\Pages;

use App\Filament\Resources\CoverageZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCoverageZones extends ListRecords
{
    protected static string $resource = CoverageZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
