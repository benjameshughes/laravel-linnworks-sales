<?php

namespace App\Console\Commands;

use App\Models\LinnworksConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptLinnworksCredentials extends Command
{
    protected $signature = 'linnworks:encrypt-credentials
                          {--force : Force re-encryption even if already encrypted}';

    protected $description = 'Encrypt existing plain-text Linnworks credentials in database';

    public function handle(): int
    {
        $this->info('Encrypting Linnworks credentials...');

        // Get all connections with raw SQL to avoid automatic decryption
        $connections = DB::table('linnworks_connections')->get();

        if ($connections->isEmpty()) {
            $this->info('No Linnworks connections found.');
            return self::SUCCESS;
        }

        $this->info("Found {$connections->count()} connection(s) to process.");

        $encrypted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            $this->line("Processing connection ID {$connection->id}...");

            try {
                // Check if already encrypted by trying to decrypt
                $needsEncryption = false;

                foreach (['application_id', 'application_secret', 'access_token', 'session_token'] as $field) {
                    $value = $connection->$field;

                    if ($value === null) {
                        continue; // Skip null values
                    }

                    try {
                        // Try to decrypt - if it works, already encrypted
                        Crypt::decryptString($value);

                        if ($this->option('force')) {
                            $this->line("  - {$field}: Re-encrypting (--force)");
                            $needsEncryption = true;
                        } else {
                            $this->line("  - {$field}: Already encrypted, skipping");
                        }
                    } catch (\Exception $e) {
                        // Decryption failed, must be plain text
                        $this->line("  - {$field}: Plain text detected, will encrypt");
                        $needsEncryption = true;
                    }
                }

                if (!$needsEncryption) {
                    $skipped++;
                    $this->info("  ✓ Connection {$connection->id} already encrypted");
                    continue;
                }

                // Encrypt plain text values
                $updates = [];

                foreach (['application_id', 'application_secret', 'access_token', 'session_token'] as $field) {
                    $value = $connection->$field;

                    if ($value === null) {
                        continue;
                    }

                    try {
                        // If --force, decrypt first then re-encrypt
                        if ($this->option('force')) {
                            try {
                                $value = Crypt::decryptString($value);
                            } catch (\Exception $e) {
                                // Already plain text, use as-is
                            }
                        }

                        // Encrypt the value
                        $updates[$field] = Crypt::encryptString($value);
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed to encrypt {$field}: " . $e->getMessage());
                        throw $e;
                    }
                }

                // Update with raw SQL to bypass model encryption
                if (!empty($updates)) {
                    DB::table('linnworks_connections')
                        ->where('id', $connection->id)
                        ->update($updates);

                    $encrypted++;
                    $this->info("  ✓ Connection {$connection->id} encrypted successfully");
                }

            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Failed to process connection {$connection->id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('Encryption complete!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Encrypted', $encrypted],
                ['Skipped (already encrypted)', $skipped],
                ['Failed', $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
