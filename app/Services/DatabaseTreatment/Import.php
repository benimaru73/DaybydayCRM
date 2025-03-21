<?php

namespace App\Services\DatabaseTreatment;

use App\Models\Client;
use App\Models\Contact;
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
                    'industry_id' => 'nullable|integer|min:0',
                    'user_id' => 'nullable|integer|min:0',
                    'primary_number' => 'nullable|string|max:20',
                    'secondary_number' => 'nullable|string|max:20'
                ]);

                if ($validator->fails()) {
                    throw new \Exception("Erreur de validation sur la ligne : " . implode(', ', $validator->errors()->all()));
                }

                $client = Client::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'vat' => $data['vat'] ?? null,
                    'company_name' => $data['company_name'],
                    'address' => $data['address'] ?? null,
                    'zipcode' => $data['zipcode'] ?? null,
                    'city' => $data['city'] ?? null,
                    'company_type' => $data['company_type'] ?? null,
                    'industry_id' => $data['industry_id'] ?? 1,
                    'user_id' => $data['user_id'] ?? 1,
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
            'success' => 'client'
        ];
    }
}