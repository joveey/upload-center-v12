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
        $mappedColumns = $mapping->columns->pluck('table_column_name')->toArray();

        if (empty($mappedColumns)) {
            return back()->with('error', 'Tidak ada kolom yang di-mapping untuk format ini.');
        }

        $actualTableColumns = DB::getSchemaBuilder()->getColumnListing($tableName);
        $validColumns = array_intersect($mappedColumns, $actualTableColumns);

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

        foreach ($validColumns as $index => $column) {
            $sheet->getColumnDimension($this->getColumnLetter($index + 1))->setAutoSize(true);
        }

        $row = 1;
        foreach ($data as $item) {
            $col = 1;
            foreach ($validColumns as $column) {
                $value = $item->{$column} ?? '';
                $sheet->setCellValue($this->getColumnLetter($col) . $row, $value);
                $col++;
            }
            $row++;
        }

        $dataRange = 'A1:' . $this->getColumnLetter(count($validColumns)) . ($row - 1);
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

