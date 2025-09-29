<?php

namespace App\Imports;

use App\Models\SpotifyUser;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DataImporter implements ToModel, WithHeadingRow
{
    /**
     * @var array
     */
    protected array $mappingRules;

    /**
     * @param array $mappingRules Aturan pemetaan dengan format: ['excel_header' => 'database_column']
     */
    public function __construct(array $mappingRules)
    {
        $this->mappingRules = $mappingRules;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row): ?Model
    {
        $dataForModel = [];

        // Secara otomatis tambahkan division_id dari pengguna yang sedang login
        // Ini adalah kunci untuk isolasi data
        $dataForModel['division_id'] = auth()->user()->division_id;

        // Loop melalui aturan pemetaan yang diberikan saat inisialisasi
        foreach ($this->mappingRules as $excelColumn => $dbColumn) {
            // Periksa apakah kolom Excel ada di baris saat ini
            // dan petakan datanya ke kolom database yang sesuai.
            if (isset($row[$excelColumn])) {
                $dataForModel[$dbColumn] = $row[$excelColumn];
            }
        }

        // Buat model SpotifyUser baru dengan data yang telah dipetakan
        return new SpotifyUser($dataForModel);
    }
}