<?php

namespace App\Http\Controllers;

use App\Models\MappingColumn;
use App\Models\MappingIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class MappingController extends Controller
{
    // ... existing content above ...

    private function listWorksheetNames(): array
    {
         = ->getPathname();
        /** @var \PhpOffice\PhpSpreadsheet\Reader\IReader  */
         = IOFactory::createReaderForFile();
        return method_exists(, 'listWorksheetNames')
            ? ->listWorksheetNames()
            : ['Sheet1'];
    }

    private function sheetRowGenerator(, string , int ): \Generator
    {
         = ->getPathname();
        /** @var \PhpOffice\PhpSpreadsheet\Reader\IReader  */
         = IOFactory::createReaderForFile();
        ->setReadDataOnly(true);

        if (method_exists(, 'setLoadSheetsOnly')) {
            ->setLoadSheetsOnly([]);
        }

         = ->load();
         = ->getSheetByName() ?? ->getSheet(0);

        foreach (->getRowIterator() as ) {
             = ->getCellIterator();
            ->setIterateOnlyExistingCells(false);
             = [];
            foreach ( as ) {
                [] = ->getValue();
            }
            yield collect();
        }

        ->disconnectWorksheets();
        unset();
    }
}
