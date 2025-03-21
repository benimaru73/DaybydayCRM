<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Services\ClientNumber\ClientNumberService;

use App\Services\DatabaseTreatment\Import;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class ImportController extends Controller
{
    protected $import;

    public function __construct(Import $import)
    {
        $this->import = $import;
    }

    public function importClient(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:2048'
        ]);

        $file = $request->file('csv_file');
        $result = $this->import->importClientsFromCsv($file);

        if (isset($result['error'])) {
            return view('databaseTreatment.fail_import', ['message' => $result['error']]);
        }

        return view('databaseTreatment.success_import', ['message' => $result['success']]);
    }

    public function showForm()
    {
        return view('databaseTreatment.clients');
    }
}
