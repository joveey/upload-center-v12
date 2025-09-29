<?php

namespace App\Imports;

use App\Models\SpotifyUser;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class DataImporter implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures; // <-- Gunakan trait ini untuk menangani kegagalan

    protected array $mappingRules;

    public function __construct(array $mappingRules)
    {
        $this->mappingRules = $mappingRules;
    }

    public function model(array $row): ?Model
    {
        $dataForModel = ['division_id' => auth()->user()->division_id];

        foreach ($this->mappingRules as $excelColumn => $dbColumn) {
            if (isset($row[strtolower(str_replace(' ', '_', $excelColumn))])) {
                $dataForModel[$dbColumn] = $row[strtolower(str_replace(' ', '_', $excelColumn)))];
            }
        }

        return new SpotifyUser($dataForModel);
    }

    /**
     * Tentukan aturan validasi untuk setiap baris.
     */
    public function rules(): array
    {
        $rules = [];
        // Balikkan mapping untuk mempermudah: ['database_column' => 'excel_header']
        $dbToExcelMap = array_flip($this->mappingRules);

        // Aturan validasi dinamis berdasarkan kolom database
        if (isset($dbToExcelMap['email'])) {
            $rules[$dbToExcelMap['email']] = 'required|email';
        }
        if (isset($dbToExcelMap['followers'])) {
            $rules[$dbToExcelMap['followers']] = 'required|integer|min:0';
        }
        if (isset($dbToExcelMap['user_id'])) {
            // Pastikan user_id unik untuk divisi yang sama
            $divisionId = auth()->user()->division_id;
            $rules[$dbToExcelMap['user_id']] = "required|unique:spotify_users,user_id,NULL,id,division_id,$divisionId";
        }

        return $rules;
    }
}