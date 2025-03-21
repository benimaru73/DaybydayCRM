<?php

namespace App\Services\DatabaseTreatment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DatabaseCleaner
{
    public function test()
    {
        return "Test Success";
    }

    public function cleanAllTablesExcept(array $exceptTables = []): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = DB::select("SHOW TABLES");
        $dbName = env('DB_DATABASE');

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_$dbName"};

            if (!in_array($tableName, $exceptTables)) {
                DB::table($tableName)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function cleanAllTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = DB::select("SHOW TABLES");
        $dbName = env('DB_DATABASE');

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_$dbName"};
            DB::table($tableName)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Artisan::call('db:seed');

    }
}
