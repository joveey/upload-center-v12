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
    public function export(Request $request, $mappingId)
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

        // Include period_date, created_at, updated_at in select if they exist
        $selectColumns = $validColumns;
        if (in_array('period_date', $actualTableColumns)) {
            $selectColumns[] = 'period_date';
        }
        if (in_array('created_at', $actualTableColumns)) {
            $selectColumns[] = 'created_at';
        }
        if (in_array('updated_at', $actualTableColumns)) {
            $selectColumns[] = 'updated_at';
        }

        $query = DB::table($tableName)->select($selectColumns);
        
        if (!$user->hasRole('super-admin')) {
            if (in_array('division_id', $actualTableColumns)) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        // TAMBAHAN: Filter by period if provided
        if ($request->has('period_date') && $request->period_date && in_array('period_date', $actualTableColumns)) {
            $query->where('period_date', $request->period_date);
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
        $colIndex = 1;
        foreach (array_keys($columnMapping) as $excelCol) {
            $dbColumn = $columnMapping[$excelCol];
            
            // Write header (using database column name or you can customize)
            $headerText = ucwords(str_replace('_', ' ', $dbColumn));
            $sheet->setCellValue($excelCol . $row, $headerText);
            $colIndex++;
        }
        
        // Add additional columns (period_date, created_at, updated_at)
        if (in_array('period_date', $actualTableColumns)) {
            $col = $this->getColumnLetter($colIndex);
            $sheet->setCellValue($col . $row, 'Period Date');
            $colIndex++;
        }
        
        if (in_array('created_at', $actualTableColumns)) {
            $col = $this->getColumnLetter($colIndex);
            $sheet->setCellValue($col . $row, 'Created At');
            $colIndex++;
        }
        
        if (in_array('updated_at', $actualTableColumns)) {
            $col = $this->getColumnLetter($colIndex);
            $sheet->setCellValue($col . $row, 'Updated At');
            $colIndex++;
        }

        // Style header row
        $headerEndCol = $this->getColumnLetter($colIndex - 1);

        $headerRange = 'A1:' . $headerEndCol . '1';
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
            $colIndex = 1;
            
            // Add mapped columns data
            foreach (array_keys($columnMapping) as $excelCol) {
                $dbColumn = $columnMapping[$excelCol];
                $value = $item->{$dbColumn} ?? '';
                $sheet->setCellValue($excelCol . $row, $value);
                $colIndex++;
            }

            // Add additional columns data
            if (in_array('period_date', $actualTableColumns)) {
                $col = $this->getColumnLetter($colIndex);
                $sheet->setCellValue($col . $row, $item->period_date ?? '');
                $colIndex++;
            }
            
            if (in_array('created_at', $actualTableColumns)) {
                $col = $this->getColumnLetter($colIndex);
                $sheet->setCellValue($col . $row, $item->created_at ?? '');
                $colIndex++;
            }
            
            if (in_array('updated_at', $actualTableColumns)) {
                $col = $this->getColumnLetter($colIndex);
                $sheet->setCellValue($col . $row, $item->updated_at ?? '');
                $colIndex++;
            }

            $row++;
        }

        // Apply borders to all data
        $dataEndCol = $this->getColumnLetter($colIndex - 1);

        $dataRange = 'A1:' . $dataEndCol . ($row - 1);
        $sheet->getStyle($dataRange)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

            // Add period to filename if filtered
        $periodSuffix = '';
        if ($request->has('period_date') && $request->period_date) {
            $periodSuffix = '_period_' . $request->period_date;
        }

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