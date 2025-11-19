<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CoverageZoneResource\Pages;
use App\Models\CoverageDepartment;
use App\Models\CoverageDistrict;
use App\Models\CoverageProvince;
use App\Models\CoverageZone;
use Filament\Forms;
use Filament\Forms\Form;      // 👈 IMPORTE CORRECTO
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;    // 👈 IMPORTE CORRECTO

class CoverageZoneResource extends Resource
{
    protected static ?string $model = CoverageZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Cobertura';
    protected static ?string $navigationLabel = 'Zonas de cobertura';
    protected static ?string $pluralModelLabel = 'Zonas de cobertura';
    protected static ?string $modelLabel = 'Zona de cobertura';

    public static function form(Form $form): Form   // 👈 Firma correcta v3
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos generales')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required(),

                        Forms\Components\Select::make('coverage_department_id')
                            ->label('Departamento')
                            ->options(fn () => CoverageDepartment::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            )
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('coverage_province_id', null)),

                        Forms\Components\Select::make('coverage_province_id')
                            ->label('Provincia')
                            ->options(fn (Get $get) => CoverageProvince::query()
                                ->when(
                                    $get('coverage_department_id'),
                                    fn ($query, $departmentId) => $query->where('coverage_department_id', $departmentId)
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            )
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('coverage_district_id', null)),

                        Forms\Components\Select::make('coverage_district_id')
                            ->label('Distrito')
                            ->options(fn (Get $get) => CoverageDistrict::query()
                                ->when(
                                    $get('coverage_province_id'),
                                    fn ($query, $provinceId) => $query->where('coverage_province_id', $provinceId)
                                )
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            )
                            ->searchable(),

                        Forms\Components\TextInput::make('score')
                            ->numeric()
                            ->label('Puntaje'),
                    ])
                    ->columns(2),

                Forms\Components\KeyValue::make('metadata')
                    ->label('Metadatos')
                    ->columnSpanFull()
                    ->addButtonLabel('Agregar metadato'),
            ]);
    }

    public static function table(Table $table): Table   // 👈 Firma correcta v3
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Zona')
                    ->searchable()
                    ->sortable()
                    ->description(fn (CoverageZone $record) => $record->district?->name),

                Tables\Columns\BadgeColumn::make('department.name')
                    ->colors(['primary'])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('province.name')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('district.name')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('score')
                    ->label('Score')
                    ->sortable()
                    ->colors([
                        'success' => fn ($state): bool => $state >= 2,
                        'warning' => fn ($state): bool => $state < 2 && $state >= 1,
                        'danger'  => fn ($state): bool => $state < 1,
                    ])
                    ->formatStateUsing(
                        fn ($state) => $state !== null
                            ? number_format($state, 2)
                            : '-'
                    ),

            ])
            ->defaultSort('name')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoverageZones::route('/'),
            'create' => Pages\CreateCoverageZone::route('/create'),
            'edit' => Pages\EditCoverageZone::route('/{record}/edit'),
            'view' => Pages\ViewCoverageZone::route('/{record}'),
        ];
    }
}
