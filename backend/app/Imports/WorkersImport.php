<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class WorkersImport implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
