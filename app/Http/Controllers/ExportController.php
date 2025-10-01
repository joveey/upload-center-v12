<?php

namespace App\Http\Controllers;

use App\Models\MappingIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ExportController extends Controller
{
    public function export($mappingId)
    {
        $user = Auth::user(); 
        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);

        if (!$user->hasRole('super-admin') && $mapping->division_id !== $user->division_id) {
            abort(403, 'Anda tidak memiliki akses untuk export format ini.');
        }

        $tableName = $mapping->table_name;
        
        // Get column mapping: excel_column => table_column_name
        $columnMapping = $mapping->columns->sortBy(function($col) {
            return ord($col->excel_column_index);
        })->pluck('table_column_name', 'excel_column_index')->toArray();

        if (empty($columnMapping)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        $actualTableColumns = DB::getSchemaBuilder()->getColumnListing($tableName);
        $validColumns = array_intersect(array_values($columnMapping), $actualTableColumns);

        if (empty($validColumns)) {
            return back()->with('error', 'Konfigurasi mapping tidak sesuai dengan skema tabel. Tidak ada kolom valid yang bisa diexport.');
        }

        $query = DB::table($tableName)->select($validColumns);
        
        if (!$user->hasRole('super-admin')) {
            if (in_array('division_id', $actualTableColumns)) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        $data = $query->get();

        if ($data->isEmpty()) {
            return back()->with('error', 'Tidak ada data untuk diexport.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set column widths
        foreach (array_keys($columnMapping) as $index => $excelCol) {
            $sheet->getColumnDimension($excelCol)->setAutoSize(true);
        }

        $row = 1;
        
        // ===== ADD HEADER ROW =====
        $col = 1;
        foreach (array_keys($columnMapping) as $excelCol) {
            $dbColumn = $columnMapping[$excelCol];
            
            // Write header (using database column name or you can customize)
            $headerText = ucwords(str_replace('_', ' ', $dbColumn));
            $sheet->setCellValue($excelCol . $row, $headerText);
            $col++;
        }
        
        // Style header row
        $headerRange = 'A1:' . $this->getColumnLetter(count($columnMapping)) . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        
        $row++; // Move to next row for data
        
        // ===== ADD DATA ROWS =====
        foreach ($data as $item) {
            $col = 1;
            foreach (array_keys($columnMapping) as $excelCol) {
                $dbColumn = $columnMapping[$excelCol];
                $value = $item->{$dbColumn} ?? '';
                $sheet->setCellValue($excelCol . $row, $value);
                $col++;
            }
            $row++;
        }

        // Apply borders to all data
        $dataRange = 'A1:' . $this->getColumnLetter(count($columnMapping)) . ($row - 1);
        $sheet->getStyle($dataRange)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $fileName = $mapping->code . '_' . date('Y-m-d_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    private function getColumnLetter($columnNumber)
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $columnNumber = ($columnNumber - $temp - 1) / 26;
        }
        return $letter;
    }
}