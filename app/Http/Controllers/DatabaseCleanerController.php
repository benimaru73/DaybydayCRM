<?php
namespace App\Http\Controllers;

use App\Services\DatabaseTreatment\DatabaseCleaner;
use Illuminate\Http\Request;

class DatabaseCleanerController extends Controller
{
    protected $cleaner;

    public function __construct(DatabaseCleaner $cleaner)
    {
        $this->cleaner = $cleaner;
    }

    public function test()
    {
        return $this->cleaner->test();
    }

    public function cleanAllTablesExcept()
    {
        $exceptTables = explode(',', env('EXCEPT_TABLES', ''));

        $this->cleaner->cleanAllTablesExcept($exceptTables); // Délègue la logique au service

        return view('databaseTreatmentTreatment.success');
    }

    public function cleanAllTables()
    {

        $this->cleaner->cleanAllTables(); // Délègue la logique au service

        return view('databaseTreatmentTreatment.success');
    }
}
