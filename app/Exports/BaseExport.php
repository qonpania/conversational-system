<?php

namespace App\Exports;

use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BaseExport extends ExcelExport implements WithStyles
{
    /**  Estilos globales de la hoja  */
    public function styles(Worksheet $sheet)
    {
        $lastCol = $sheet->getHighestColumn();   // p. ej. "L"
        $lastRow = $sheet->getHighestRow();      // número de última fila

        // ► Encabezado: azul corporativo, texto blanco, negrita
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '0066CC']],
        ]);

        // ► Bordes finos en todo el rango (encabezado + datos)
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")
              ->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
}
