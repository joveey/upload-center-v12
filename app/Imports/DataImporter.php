<?php

namespace App\Imports;

use App\Models\SpotifyUser;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class DataImporter implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    protected array $mappingRules;

    public function __construct(array $mappingRules)
    {
        $this->mappingRules = $mappingRules;
    }

    /**
     * Transform each row into a SpotifyUser model
     */
    public function model(array $row): ?SpotifyUser
    {
        $user = Auth::user();
        $dataForModel = ['division_id' => $user ? $user->division_id : null];

        foreach ($this->mappingRules as $excelColumn => $dbColumn) {
            $normalizedKey = strtolower(str_replace(' ', '_', $excelColumn));
            if (isset($row[$normalizedKey])) {
                $dataForModel[$dbColumn] = $row[$normalizedKey];
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
            $normalizedKey = strtolower(str_replace(' ', '_', $dbToExcelMap['email']));
            $rules[$normalizedKey] = 'required|email';
        }
        
        if (isset($dbToExcelMap['followers'])) {
            $normalizedKey = strtolower(str_replace(' ', '_', $dbToExcelMap['followers']));
            $rules[$normalizedKey] = 'required|integer|min:0';
        }
        
        if (isset($dbToExcelMap['user_id'])) {
            // Pastikan user_id unik untuk divisi yang sama
            $user = Auth::user();
            $divisionId = $user ? $user->division_id : null;
            $normalizedKey = strtolower(str_replace(' ', '_', $dbToExcelMap['user_id']));
            $rules[$normalizedKey] = "required|unique:spotify_users,user_id,NULL,id,division_id,{$divisionId}";
        }

        return $rules;
    }

    /**
     * Custom attribute names for validation error messages
     */
    public function customValidationAttributes(): array
    {
        $attributes = [];
        $dbToExcelMap = array_flip($this->mappingRules);

        foreach ($dbToExcelMap as $dbColumn => $excelColumn) {
            $normalizedKey = strtolower(str_replace(' ', '_', $excelColumn));
            $attributes[$normalizedKey] = $excelColumn;
        }

        return $attributes;
    }
}