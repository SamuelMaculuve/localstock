<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Get the file PATH from config, not the content
        $sqlFilePath = config('app.sql_path');
        $lqsFile = storage_path('lqs');
        $getInfoFile = storage_path('info');

        try {
            // Check if SQL file exists - use the PATH, not content
            if (!File::exists($sqlFilePath)) {
                $this->command->error("Demo SQL file not found: " . $sqlFilePath);
                $this->command->info("Current directory: " . base_path());
                $this->command->info("Available files in database directory:");
                $files = File::files(database_path());
                foreach ($files as $file) {
                    $this->command->info(" - " . $file->getFilename());
                }
                return;
            }

            $this->command->info("Found SQL file: " . $sqlFilePath);

            // Read SQL file - now using the correct path
            $sql = File::get($sqlFilePath);

            if (empty($sql)) {
                $this->command->error("SQL file is empty");
                return;
            }

            $this->command->info("SQL file loaded, size: " . strlen($sql) . " bytes");

            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            $this->command->info('Executing SQL statements...');

            // Execute SQL
            DB::unprepared($sql);

            // Enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Create installation log
            $installedLogFile = storage_path('installed');
            if (file_exists($getInfoFile)) {
                $data = file_get_contents($getInfoFile);
                unlink($getInfoFile);
            } else {
                $data = json_encode([
                    'd' => base64_encode($this->get_domain_name(url()->full())), // Fixed: use url() helper instead of request()
                    'i' => date('ymdhis'),
                    'u' => date('ymdhis'),
                ]);
            }

            if (!file_exists($installedLogFile)) {
                file_put_contents($installedLogFile, $data);
                $this->command->info('✓ Installation log created');
            }

            $this->command->info('✓ Demo database imported successfully!');

        } catch (\Exception $e) {
            // Clean up on error
            if (file_exists($lqsFile)) {
                unlink($lqsFile);
            }
            if (file_exists($getInfoFile)) {
                unlink($getInfoFile);
            }

            // Re-enable foreign key checks in case of error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->command->error('✗ Error importing demo database: ' . $e->getMessage());
            $this->command->error('Error trace: ' . $e->getTraceAsString());
        }

        // Criar usuários de teste DEPOIS do SQL (para não serem apagados)
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Criando usuários de teste...');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->call(UserSeeder::class);
    }

    private function is_valid_domain_name($url)
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $host) &&
            preg_match("/^.{1,253}$/", $host) &&
            preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $host));
    }

    /**
     * Extract domain name from URL
     */
    private function get_domain_name($url)
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        // Remove www. if present
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        return $host;
    }
}
