<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class DailyAssignmentsImport implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
