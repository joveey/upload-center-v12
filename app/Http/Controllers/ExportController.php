<?php
// app/Http/Controllers/ExportController.php

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

class ExportController extends Controller
{
    public function export($mappingId)
    {
        $user = Auth::user();
        $mapping = MappingIndex::with('columns')->findOrFail($mappingId);

        // Cek permission
        if (!$user->division->isSuperUser() && $mapping->division_id !== $user->division_id) {
            abort(403, 'Anda tidak memiliki akses untuk export format ini.');
        }

        $tableName = $mapping->table_name;
        $columns = $mapping->columns->pluck('table_column_name')->toArray();

        if (empty($columns)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        $query = DB::table($tableName)->select($columns);
        
        // Filter berdasarkan divisi jika bukan SuperUser
        if (!$user->division->isSuperUser()) {
            // Cek apakah table punya kolom division_id
            if (in_array('division_id', $columns) || DB::getSchemaBuilder()->hasColumn($tableName, 'division_id')) {
                $query->where('division_id', $user->division_id);
            }
        }
        
        $data = $query->get();

        if ($data->isEmpty()) {
            return back()->with('error', 'Tidak ada data untuk diexport.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header styling
        $headerRange = 'A1:' . $this->getColumnLetter(count($columns)) . '1';
        $sheet->getStyle($headerRange)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF6366F1');

        $sheet->getStyle($headerRange)
            ->getFont()
            ->setBold(true)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));

        $sheet->getStyle($headerRange)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Set headers
        foreach ($columns as $index => $column) {
            $columnMapping = $mapping->columns->firstWhere('table_column_name', $column);
            $displayName = $columnMapping ? $columnMapping->excel_column_index : $column;
            $sheet->setCellValue($this->getColumnLetter($index + 1) . '1', $displayName);
            $sheet->getColumnDimension($this->getColumnLetter($index + 1))->setAutoSize(true);
        }

        // Set data
        $row = 2;
        foreach ($data as $item) {
            $col = 1;
            foreach ($columns as $column) {
                $value = $item->{$column} ?? '';
                $sheet->setCellValue($this->getColumnLetter($col) . $row, $value);
                $col++;
            }
            $row++;
        }

        // Border
        $dataRange = 'A1:' . $this->getColumnLetter(count($columns)) . ($row - 1);
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