<?php

namespace App\Services\DatabaseTreatment;

use App\Enums\OfferStatus;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Industry;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\ClientNumber\ClientNumberService;

use Faker\Factory as Faker;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\Unit\Status\TypeOfStatusTest;

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


    public function import($file, $file2,$file3)
    {
        $handle = fopen($file->getPathname(), "r");
        $header = fgetcsv($handle, 1000, ";");
        $handle2 = fopen($file2->getPathname(), "r");
        $header2 = fgetcsv($handle2, 1000, ";");
        $handle3 = fopen($file3->getPathname(), "r");
        $header3 = fgetcsv($handle3, 1000, ";");
        $faker = Faker::create();

        DB::beginTransaction();
        $errors = [];
        //
            $lineNumber = 1;
            while (($row = fgetcsv($handle, 1000, ";")) !== false) {
                $lineNumber++;
                $data = array_combine($header, $row);

                    $validator1 = Validator::make($data, [
                        'project_title' => 'required|string|max:255',
                        'client_name' => 'required|string|max:255'
                    ]);

                    if ($validator1->fails()) {
                        $errors[] = "Erreur de validation ligne $lineNumber (fichier 1) : " . implode(', ', $validator1->errors()->all());
                        continue;
                    }

                    $client = Client::where('company_name', $data['client_name'])->first();

                    if (!$client) {
                        $client = Client::create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'company_name' => $data['client_name'],
                            'industry_id' => 1,
                            'user_id' => 1,
                            'client_number' => app(ClientNumberService::class)->setNextClientNumber(),
                            'is_primary' => true

                        ]);
                        Contact::create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'name' => $faker->name,      // Génère un nom aléatoire
                            'email' => $faker->unique()->safeEmail,
                            'client_id' => $client->id,
                            'is_primary' => true
                        ]);
                    }

                    if (!$client) {
                        $errors[] = "Erreur ligne $lineNumber (fichier 1) : Impossible de créer ou récupérer le client '{$data['client_name']}'";
                        continue;
                    }

                    if (!empty($data['project_title'])) {
                        Project::create([
                            'external_id' => Uuid::uuid4()->toString(),
                            'title' => $data['project_title'],
                            'client_id' => $client->id,
                            'status_id' => 11,
                            'user_assigned_id' => 1,
                            'user_created_id' => 1,
                            'deadline' => Carbon::now()->addMonth()->addSeconds(rand(0, Carbon::now()->addMonthNoOverflow()->diffInSeconds(Carbon::now()->addMonths(2)))),
                            'is_primary' => true
                        ]);
                    }
            }

//file 2
            $lineNumber = 1;
            while (($row = fgetcsv($handle2, 1000, ";")) !== false) {
                $lineNumber++;

                $data = array_combine($header2, $row);


                    $validator2 = Validator::make($data, [
                        'project_title' => 'required|string|max:255',
                        'task_title' => 'required|string|max:255'
                    ]);

                    if ($validator2->fails()) {
                        $errors[] = "Erreur de validation ligne $lineNumber (fichier 2) : " . implode(', ', $validator2->errors()->all());
                        continue;
                    }

                    $project = Project::where('title', $data['project_title'])->first();

                    if (!$project) {
                        $errors[] = "Erreur ligne $lineNumber (fichier 2) : Projet introuvable pour '{$data['project_title']}'";
                        continue;
                    }

                    Task::create([
                        'external_id' => Uuid::uuid4()->toString(),
                        'title' => $data['task_title'],
                        'status_id' => 1,
                        'user_assigned_id' => 1,
                        'user_created_id' => 1,
                        'client_id' => $project->client_id,
                        'project_id' => $project->id,
                        'is_primary' => true

                    ]);
            }

        $lineNumber = 1;
        while (($row = fgetcsv($handle3, 1000, ";")) !== false) {
            $lineNumber++;
            $data = array_combine($header3, $row);

            $validator1 = Validator::make($data, [
                'client_name' => 'required|string|max:255',
                'lead_title' => 'required|string|max:255',
                'type' => 'required|string|max:255',
                'produit' => 'required|string|max:255',
                'prix' => 'required|Integer|min:0',
                'quantite' => 'required|Integer|min:0'
            ]);

            if ($validator1->fails()) {
                $errors[] = "Erreur de validation ligne $lineNumber (fichier 3) : " . implode(', ', $validator1->errors()->all());
                continue;
            }

            $client = Client::where('company_name', $data['client_name'])->first();

            if (!$client) {
                $client = Client::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'company_name' => $data['client_name'],
                    'industry_id' => 1,
                    'user_id' => 1,
                    'client_number' => app(ClientNumberService::class)->setNextClientNumber(),
                    'is_primary' => true
                ]);
            }

            $product = Product::where('name', $data['produit'])->first();
            if (!$product) {
                $product = Product::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'name' => $data['produit'],
                    'price' => $data['prix'],
                    'default_type' => "pieces",
                    'is_primary' => true
                ]);
            }

            if (!$client) {
                $errors[] = "Erreur ligne $lineNumber (fichier 1) : Impossible de créer ou récupérer le client '{$data['client_name']}'";
                continue;
            }

            $lead = Lead::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'title' => $data['lead_title'],
                    'description' => $faker->sentence(),
                    'status_id' => 7,
                    'user_assigned_id' => 1,
                    'user_created_id' => 1,
                    'client_id' => $client->id,
                    'qualified' => 0,
                    'deadline' => Carbon::now()->addDay(4),
                    'is_primary' => true
                ]);

            if ($data['type']=="offers") {
                $offer = Offer::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'title' => $data['lead_title'],
                    'client_id' => $client->id,
                    'source_type' => Lead::class,
                    'source_id' => $lead->id,
                    'status' => OfferStatus::inProgress()->getStatus(),
                    'is_primary' => true
                ]);
                InvoiceLine::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'title' => $data['lead_title'],
                    'price' => $data['prix'],
                    'offer_id' => $offer->id,
                    'type' => $product->default_type,
                    'product_id' => $product->id,
                    'quantity' => $data['quantite'],
                    'is_primary' => true

                ]);
            }elseif ($data['type']=="invoice"){
                $offer = Offer::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'client_id' => $client->id,
                    'source_type' => Lead::class,
                    'source_id' => $lead->id,
                    'status' => OfferStatus::won()->getStatus(),
                    'is_primary' => true
                ]);
                InvoiceLine::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'title' => $data['lead_title'],
                    'price' => $data['prix'],
                    'offer_id' => $offer->id,
                    'type' => $product->default_type,
                    'quantity' => $data['quantite'],
                    'product_id' => $product->id,
                    'is_primary' => true
                ]);
                $invoice = Invoice::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'status' => "draft",
                    "invoice_number" => 1,
                    "client_id" => $client->id,
                    "offer_id" => $offer->id,
                    "due_at" => Carbon::now()->addDay(14),
                    'is_primary' => true
                ]);
                InvoiceLine::create([
                    'external_id' => Uuid::uuid4()->toString(),
                    'title' => $data['lead_title'],
                    'price' => $data['prix'],
                    'invoice_id' => $invoice->id,
                    'type' => $product->default_type,
                    'product_id' => $product->id,
                    'quantity' => $data['quantite'],
                    'is_primary' => true
                ]);
            }
        }

        if (!empty($errors)) {
            DB::rollBack();
            return ['errors' => $errors];
        }
        DB::commit();
        fclose($handle);
        fclose($handle2);
        fclose($handle3);

        return ['success' => "Importation terminée"];
    }

}