<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    // protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?int $navigationSort = 2;

    public function getHeading(): string
    {
        return 'Backups de la app';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuración';
    }
}
