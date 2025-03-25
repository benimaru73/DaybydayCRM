<?php

namespace App\Services\DatabaseTreatment;

use App\Models\Client;
use App\Models\Industry;
use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use App\Models\Product;
use App\Models\Lead;
use App\Models\Invoice;
use Faker\Factory as Faker;
use App\Models\Offer;
use App\Models\InvoiceLine;
use App\Models\Contact;

class ImportService
{
    public function importProjectAndClient($filename)  {
        $faker = Faker::create();

        // Open the CSV file
        if (($handle = fopen($filename, 'r')) !== false) {
            // Read the header row
            $header = fgetcsv($handle, 1000, ';');

            // Loop through the file line by line
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                // Create an associative array with the header as keys
                $row = array_combine($header, $data);

                $userId = User::inRandomOrder()->value('id') ?? factory(User::class)->create()->id;
                $industryId = Industry::inRandomOrder()->value('id') ?? factory(Industry::class)->create()->id;

                $client = Client::firstOrCreate(
                    ['company_name' => $row['client_name']],
                    ['external_id' => $faker->uuid,
                        'address' => $faker->address,
                        'zipcode' => $faker->postcode,
                        'city' => $faker->city,
                        'company_type' => 'ApS',
                        'industry_id' => $industryId,
                        'user_id' => $userId,
                    ]);

                $contact = Contact::firstOrCreate(
                    ['client_id' => $client->id],
                    [
                        'external_id' => $faker->uuid,
                        'name' =>$row['client_name'],
                        'email' => $faker->email,
                        'primary_number' => $faker->phoneNumber,
                        'secondary_number' => $faker->phoneNumber,
                        'is_primary' => 1,
                    ]);

                $userAssignedId = User::inRandomOrder()->value('id') ?? factory(User::class)->create()->id;
                $userCreatedId = User::inRandomOrder()->value('id') ?? factory(User::class)->create()->id;

                // Insert data into the project table
                Project::firstOrCreate(
                    ['title' => $row['project_title']],
                    [
                        'external_id' => $faker->uuid,
                        'description' => $faker->sentence,
                        'client_id' => $client->id,
                        'status_id' => 11,
                        'user_assigned_id' => $userAssignedId,
                        'user_created_id' => $userCreatedId,
                    ]);
            }

            // Close the file
            fclose($handle);
        }
    }

    public function importTask($filename) {
        $faker = Faker::create();

        // Open the CSV file
        if (($handle = fopen($filename, 'r')) !== false) {
            // Read the header row
            $header = fgetcsv($handle, 1000, ';');


            // Loop through the file line by line
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {

                // Create an associative array with the header as keys
                $row = array_combine($header, $data);

                $projectId = Project::where('title', $row['project_title'])->value('id');
                $userAssignedId = User::inRandomOrder()->value('id') ?? factory(User::class)->create()->id;
                $userCreatedId = User::inRandomOrder()->value('id') ?? factory(User::class)->create()->id;
                $clientId = Project::where('title', $row['project_title'])->value('client_id');

                // Insert data into the task table
                Task::firstOrCreate(
                    ['title' => $row['task_title']],
                    [
                        'external_id' => $faker->uuid,
                        'description' => $faker->sentence,
                        'project_id' => $projectId,
                        'status_id' => 1,
                        'user_assigned_id' => $userAssignedId,
                        'user_created_id' => $userCreatedId,
                        'client_id' => $clientId,
                    ]);
            }

            // Close the file
            fclose($handle);
        }
    }

    public function importLeadProductInvoice($filename , $realFileName)  {
        $faker = Faker::create();
        $error = [];
        // Open the CSV file
        if (($handle = fopen($filename, 'r')) !== false) {
            // Read the header row
            $header = fgetcsv($handle, 1000, ';');
            $lineNumber = 1;

            // Loop through the file line by line
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                // Create an associative array with the header as keys
                $row = array_combine($header, $data);
                $lineNumber++;

                // $projectId = Project::where('title', $row['project_title'])->value('id');
                $userAssignedId = User::inRandomOrder()->value('id') ?? factory(User::class)->create()->id;
                $userCreatedId = User::inRandomOrder()->value('id') ?? factory(User::class)->create()->id;
                $clientId = Client::where('company_name' , $row['client_name'])->value('id');

                if ($row['prix'] < 0) {
                    $error[]=[
                        'file' => $realFileName,
                        'line' => $lineNumber,
                        'message' => 'Price cannot be negative',
                    ];
                }

                if ($row['quantite'] < 0) {
                    $error[]=[
                        'file' => $realFileName,
                        'line' => $lineNumber,
                        'message' => 'Quantity cannot be negative',
                    ];

                    continue;
                }

                // Insert product
                $product = Product::firstOrcreate(
                    ['name' => $row['produit']],
                    ['external_id' => $faker->uuid,
                        'price' => $row['prix'],
                        'number' => $faker->randomDigit,
                        'description' => $faker->sentence,
                        'default_type' => $faker->randomElement(['hour', 'product']),
                    ]);

                $lead = Lead::firstOrcreate(
                    ['title' => $row['lead_title']],
                    [
                        'external_id' => $faker->uuid,
                        'description' => $faker->sentence,
                        'status_id' => 7,
                        'user_assigned_id' => $userAssignedId,
                        'user_created_id' =>$userCreatedId,
                        'client_id' => $clientId,
                        'deadline' => now()->addMonth(),
                    ]);

                $statusOffer = $row['type'] == 'invoice' ? 'won' : 'in-progress';

                $offer = Offer::create(
                    [
                        'status' => $statusOffer,
                        'source_id' =>$lead->id,
                        'external_id' => $faker->uuid,
                        'source_type' => Lead::class,
                        'client_id' => $clientId,
                    ]);

                // check the type
                if ($row['type'] == 'invoice') {
                    // create invoice
                    $invoice = Invoice::create(
                        [
                            'external_id' => $faker->uuid,
                            'status' => 'draft',
                            'invoice_number' => $faker->randomDigit,
                            'source_type' => Lead::class,
                            'source_id' => $lead->id,
                            'due_at' => now()->addMonth(),
                            'offer_id' => $offer->id,
                            'client_id' => $clientId,
                        ]);

                    InvoiceLine::create(
                        [
                            'external_id' => $faker->uuid,
                            'title' => $row['produit'],
                            'type' => $product->default_type,
                            'comment' => $faker->sentence,
                            'quantity' => $row['quantite'],
                            'price' => $row['prix'],
                            'offer_id' => $offer->id,
                        ]
                    );

                    InvoiceLine::create(
                        [
                            'external_id' => $faker->uuid,
                            'title' => $row['produit'],
                            'type' => $product->default_type,
                            'comment' => $faker->sentence,
                            'quantity' => $row['quantite'],
                            'price' => $row['prix'],
                            'invoice_id' => $invoice->id,
                        ]
                    );

                }elseif ($row['type'] == 'offers') {
                    InvoiceLine::create(
                        [
                            'external_id' => $faker->uuid,
                            'title' => $row['produit'],
                            'type' => $product->default_type,
                            'comment' => $faker->sentence,
                            'quantity' => $row['quantite'],
                            'price' => $row['prix'],
                            'offer_id' => $offer->id,
                        ]
                    );
                }

            }

            // Close the file
            fclose($handle);
        }

        return $error;
    }
}