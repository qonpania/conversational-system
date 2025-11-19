<?php

namespace App\Filament\Pages;

use App\Services\Coverage\ImportCoverageKmlService;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class UploadCoverageKml extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Subir KML de cobertura';
    protected static ?string $navigationGroup = 'Cobertura';
    protected static ?string $slug            = 'coverage/upload-kml';
    protected static ?string $title           = 'Subir KML de cobertura';

    protected static string $view = 'filament.pages.upload-coverage-kml';

    /**
     * Estado interno del formulario.
     *
     * data['kml_file']         => ruta del archivo en el disco
     * data['replace_existing'] => bool
     * data['notes']            => string|null
     */
    public ?array $data = [];

    /**
     * Resumen del último import, para mostrarlo en la vista.
     *
     * Ejemplo:
     * [
     *   'zones'    => 120,
     *   'polygons' => 350,
     *   'districts'=> 42,
     * ]
     */
    public ?array $lastImportSummary = null;

    public function mount(): void
    {
        // Estado inicial del formulario
        $this->form->fill([
            'replace_existing' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Archivo KML de cobertura')
                    ->description('Sube el archivo KML con las zonas de cobertura para que el sistema pueda validar coordenadas de latitud/longitud.')
                    ->schema([
                        FileUpload::make('kml_file')
    ->label('Archivo KML')
    ->disk('local')
    ->directory('coverage/kml')
    // ->acceptedFileTypes(['.kml'])  // ⚠️ comenta esto para probar
    ->required()
    ->maxSize(1024 * 10)
    ->helperText('Solo se admite un archivo .kml. Tamaño máximo: 10 MB.')
    ->preserveFilenames()
    ->visibility('private'),


                        Toggle::make('replace_existing')
                            ->label('Reemplazar datos existentes')
                            ->helperText('Si está activado, se borrarán las zonas de cobertura anteriores antes de importar el nuevo KML.'),

                        Textarea::make('notes')
                            ->label('Notas internas')
                            ->placeholder('Ej: Versión del KML, fuente, fecha de generación…')
                            ->rows(3),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    /**
     * Acción principal del formulario (se llama desde el botón "Procesar KML").
     */
    public function submit(): void
    {
        $data = $this->form->getState();

        if (empty($data['kml_file'])) {
            Notification::make()
                ->title('Debes seleccionar un archivo KML.')
                ->danger()
                ->send();

            return;
        }

        // Ruta relativa en el disco configurado en el FileUpload
        $relativePath = $data['kml_file'];

        // Disco usado (coincide con el FileUpload)
        $disk = 'local'; // o el que tengas en FileUpload::make('kml_file')->disk(...)

        // Path absoluto solo para validar que existe
        $absolutePath = Storage::disk($disk)->path($relativePath);

        if (! file_exists($absolutePath)) {
            Notification::make()
                ->title('No se encontró el archivo KML en el servidor.')
                ->danger()
                ->send();

            return;
        }

        // Encolar el job en la cola "default"
        ProcessCoverageKmlJob::dispatch(
            relativePath: $relativePath,
            replaceExisting: (bool) ($data['replace_existing'] ?? false),
            notes: $data['notes'] ?? null,
            userId: auth()->id(),
        )->onQueue('default'); // opcional, si quieres ser explícito

        // Como es asíncrono, ya no podemos mostrar el resumen aquí
        $this->lastImportSummary = null;

        Notification::make()
            ->title('Importación encolada')
            ->body('El archivo KML se está procesando en segundo plano. Podrás ver las zonas de cobertura cuando termine el job.')
            ->success()
            ->send();
    }

    /**
     * Acción auxiliar para limpiar el formulario y el resumen.
     */
    public function resetForm(): void
    {
        $this->data = [];
        $this->lastImportSummary = null;

        $this->form->fill([
            'replace_existing' => false,
        ]);
    }
}
