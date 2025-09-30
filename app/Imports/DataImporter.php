<?php

namespace App\Imports;

use App\Models\SpotifyUser;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;

class DataImporter implements ToModel, WithStartRow, WithValidation, SkipsOnFailure
{
    protected array $mapping;
    protected int $headerRow;
    protected $failures = [];

    public function __construct(array $mapping, int $headerRow)
    {
        $this->mapping = $mapping;
        $this->headerRow = $headerRow;
    }

    public function model(array $row): SpotifyUser
    {
        $mappedData = [];
        foreach ($this->mapping as $excelHeader => $dbColumn) {
            $excelHeaders = array_keys($row);
            $key = array_search(strtolower($excelHeader), array_map('strtolower', $excelHeaders));
            if ($key !== false) {
                $mappedData[$dbColumn] = $row[$excelHeader];
            }
        }

        return new SpotifyUser($mappedData);
    }

    public function startRow(): int
    {
        return $this->headerRow + 1;
    }

    public function rules(): array
    {
        return [
             '*.email' => ['nullable', 'email', 'unique:spotify_users,email'],
             '*.followers' => ['nullable', 'integer'],
        ];
    }
    
    public function onFailure(Failure ...$failures)
    {
        $this->failures = array_merge($this->failures, $failures);
    }

    public function getFailures()
    {
        return collect($this->failures);
    }
}