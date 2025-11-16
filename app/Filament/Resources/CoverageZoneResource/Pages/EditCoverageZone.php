<?php

namespace App\Filament\Resources\CoverageZoneResource\Pages;

use App\Filament\Resources\CoverageZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoverageZone extends EditRecord
{
    protected static string $resource = CoverageZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
