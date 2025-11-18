<?php

namespace App\Filament\Resources\CoverageZoneResource\Pages;

use App\Filament\Resources\CoverageZoneResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCoverageZone extends ViewRecord
{
    protected static string $resource = CoverageZoneResource::class;

    /**
     * Usamos una vista Blade personalizada para poder incrustar el mapa.
     */
    protected static string $view = 'filament.resources.coverage-zone-resource.pages.view-coverage-zone';
}
