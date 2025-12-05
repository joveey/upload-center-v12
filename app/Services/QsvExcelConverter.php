<?php

namespace App\Services;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class QsvExcelConverter
{
    /**
     * Convert XLSX to CSV using qsv (binary must be available on the host).
     *
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function convertXlsxToCsv(string $inputPath, string $outputPath, ?string $sheetName = null): void
    {
        $binary = config('services.qsv.path', 'qsv');
        $resolvedInput = realpath($inputPath);
        if (! $resolvedInput) {
            throw new \RuntimeException("File not found: {$inputPath}");
        }

        if (file_exists($outputPath)) {
            @unlink($outputPath);
        }

        $tmpDir = storage_path('app/tmp/qsv-temp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $command = [$binary, 'excel', $resolvedInput];

        if (! empty($sheetName)) {
            $command[] = '--sheet';
            $command[] = $sheetName;
        }

        $env = [
            'TMP' => $tmpDir,
            'TEMP' => $tmpDir,
        ];

        $process = new Process($command, null, $env);
        $process->run(null);

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        file_put_contents($outputPath, $process->getOutput());
    }
}
