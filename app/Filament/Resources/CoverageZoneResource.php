<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CoverageZoneResource\Pages;
use App\Models\CoverageZone;
use Filament\Forms;
use Filament\Forms\Form;      // 👈 IMPORTE CORRECTO
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

                        Forms\Components\TextInput::make('departamento'),
                        Forms\Components\TextInput::make('provincia'),
                        Forms\Components\TextInput::make('distrito'),

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
                    ->description(fn (CoverageZone $record) => $record->distrito),

                Tables\Columns\BadgeColumn::make('departamento')
                    ->colors(['primary'])
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('provincia')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('distrito')
                    ->sortable()
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
            ->defaultSort('distrito')
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
