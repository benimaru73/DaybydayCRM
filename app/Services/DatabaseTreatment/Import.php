<?php

namespace App\Services\DatabaseTreatment;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Industry;
use App\Models\User;
use App\Services\ClientNumber\ClientNumberService;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class Import
{
    public function importClientsFromCsv($file)
    {
        $handle = fopen($file->getPathname(), "r");

        if ($handle === false) {
            return ['error' => 'Impossible de lire le fichier.'];
        }

        $header = fgetcsv($handle, 1000, ";");

        $industries = Industry::pluck('id', 'name')->toArray();
        $users = User::pluck('id', 'name')->toArray();

        $clients = [];
        $contacts = [];

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle, 1000, ";")) !== false) {
                $data = array_combine($header, $row);

                $validator = Validator::make($data, [
                    'name' => 'nullable|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'company_name' => 'required|string|max:255',
                    'vat' => 'nullable|string|max:50',
                    'address' => 'nullable|string|max:255',
                    'zipcode' => 'nullable|string|max:20',
                    'city' => 'nullable|string|max:255',
                    'company_type' => 'nullable|string|max:50',
                    'industry' => 'nullable|string|max:250',
                    'user' => 'nullable|string|max:255',
                    'primary_number' => 'nullable|string|max:20',
                    'secondary_number' => 'nullable|string|max:20'
                ]);

                if ($validator->fails()) {
                    throw new \Exception("Error validation on line : " . implode(', ', $validator->errors()->all()));
                }
//                if (isset($data['industry']) && $industries[$data['industry']] == null){
//                    throw new \Exception("Error industry is a foreign key industry");
//                }
//                if (isset($data['user']) && $users[$data['user']] == null){
//                    throw new \Exception("Error user is a foreign key industry");
//                }

                $industryId = $industries[$data['industry']] ?? 1;
//                $userId = $users[$users['user']] ?? 1;
                $userId = 1;


                $client = Client::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'vat' => $data['vat'] ?? null,
                    'company_name' => $data['company_name'],
                    'address' => $data['address'] ?? null,
                    'zipcode' => $data['zipcode'] ?? null,
                    'city' => $data['city'] ?? null,
                    'company_type' => $data['company_type'] ?? null,
                    'industry_id' => $industryId,
                    'user_id' => $userId,
                    'client_number' => app(ClientNumberService::class)->setNextClientNumber(),
                ]);

                $clients[] = $client;

                if (!empty($data['name']) && !empty($data['email'])) {
                    $contact = Contact::create([
                        'external_id' => Uuid::uuid4()->toString(),
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'primary_number' => $data['primary_number'] ?? null,
                        'secondary_number' => $data['secondary_number'] ?? null,
                        'client_id' => $client->id,
                        'is_primary' => true
                    ]);

                    $contacts[] = $contact;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ['error' => 'Erreur lors de l\'importation: ' . $e->getMessage()];
        } finally {
            fclose($handle);
        }


        return [
            'success' => 'de client '.count($clients).' de nombre'
        ];
    }

}