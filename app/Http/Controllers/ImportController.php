<?php
namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Services\ClientNumber\ClientNumberService;

use App\Services\DatabaseTreatment\Import;
use App\Services\DatabaseTreatment\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class ImportController extends Controller
{
    protected $import;
    protected $importService;

    public function __construct(Import $import, ImportService $importService)
    {
        $this->import = $import;
        $this->importService = $importService;
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

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:2048'
        ]);

        $file = $request->file('csv_file');
        $file2 = $request->file('csv_file2');
        $file3 = $request->file('csv_file3');
        $realFileName = $file->getClientOriginalName();
//        $result = $this->import->import($file,$file2,$file3);
        DB::beginTransaction(); // Début de la transaction

            // Exécuter les trois fonctions d'importation
            $this->importService->importProjectAndClient($file);
            $this->importService->importTask($file2);
            $errors = $this->importService->importLeadProductInvoice($file3, $file3->getClientOriginalName());

            if (!empty($errors)) {
                DB::rollBack();
                return view('databaseTreatment.fail_import', ['message' => $errors]);
            }

            DB::commit();
        return view('databaseTreatment.success_import', ['message' => 'success']);
    }

    public function evalForm()
    {
        return view('databaseTreatment.evalForm');
    }
}
