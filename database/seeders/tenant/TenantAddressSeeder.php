<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Seeder for importing address data (divisions, districts, areas) into tenant database.
 */
class TenantAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $connection = DB::connection('tenant');

        // Disable foreign key checks
        $connection->statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // Truncate tables first (to avoid duplicate entry errors)
            $this->command->info('Truncating address tables...');
            $connection->table('areas')->truncate();
            $connection->table('districts')->truncate();
            $connection->table('divisions')->truncate();

            // Seed in correct order (parent to child relationships)
            $this->seedFromFile($connection, database_path('seeders/tenant/sql/divisions.sql'));
            $this->seedFromFile($connection, database_path('seeders/tenant/sql/districts.sql'));
            $this->seedFromFile($connection, database_path('seeders/tenant/sql/areas.sql'));

            $this->command->info('Address data seeded successfully!');
        } finally {
            // Re-enable foreign key checks
            $connection->statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Read SQL file and execute only INSERT statements.
     *
     * @param \Illuminate\Database\Connection $connection
     * @param string $path
     * @return void
     */
    private function seedFromFile($connection, string $path): void
    {
        if (!File::exists($path)) {
            $this->command->warn("SQL file not found: {$path}");
            return;
        }

        $this->command->info("Seeding from: {$path}");

        // Read the file content
        $sql = File::get($path);

        // Strip everything before the first INSERT INTO
        $insertPosition = stripos($sql, 'INSERT INTO');
        if ($insertPosition === false) {
            $this->command->warn("No INSERT statements found in: {$path}");
            return;
        }

        $sql = substr($sql, $insertPosition);

        // Clean up SQL: remove MySQL session variable comments
        // These lines cause errors: /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
        $sql = preg_replace('/\/\*!.*?\*\/;?/s', '', $sql);
        
        // Remove any trailing semicolons and whitespace after the main INSERT
        $sql = trim($sql);
        
        // Find the end of the INSERT statement (before any trailing comments)
        // Look for the last semicolon that's part of the VALUES clause
        if (preg_match('/INSERT INTO.*?VALUES.*?;/is', $sql, $matches)) {
            $sql = $matches[0];
        }

        // Execute the INSERT statements
        $connection->unprepared($sql);
    }
}
