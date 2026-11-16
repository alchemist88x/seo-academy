<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certificates', 'encrypted_verification_code')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->text('encrypted_verification_code')->nullable()->after('verification_code');
            });
        }

        // Backfill existing certificates: convert plaintext verification_code to encrypted_verification_code
        // and ensure verification_token_hash is populated.
        $certificates = DB::table('certificates')
            ->select('id', 'verification_code', 'verification_token_hash', 'encrypted_verification_code')
            ->get();

        foreach ($certificates as $cert) {
            $updates = [];

            // If plaintext exists and encrypted is not yet set
            if (! empty($cert->verification_code) && empty($cert->encrypted_verification_code)) {
                $encrypted = Crypt::encryptString($cert->verification_code);

                // Verify decryption matches before clearing plaintext
                $verified = Crypt::decryptString($encrypted);
                if ($verified !== $cert->verification_code) {
                    throw new RuntimeException("Encryption verification failed for certificate ID {$cert->id}");
                }

                $updates['encrypted_verification_code'] = $encrypted;
                $updates['verification_code'] = null; // Plaintext cleared safely after verification
            }

            // Ensure verification_token_hash is populated
            if (empty($cert->verification_token_hash)) {
                $codeToHash = ! empty($cert->verification_code)
                    ? $cert->verification_code
                    : (! empty($updates['encrypted_verification_code']) ? Crypt::decryptString($updates['encrypted_verification_code']) : null);

                if ($codeToHash !== null) {
                    $updates['verification_token_hash'] = hash('sha256', $codeToHash);
                }
            }

            if (! empty($updates)) {
                DB::table('certificates')
                    ->where('id', $cert->id)
                    ->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Reversible rollback: restore plaintext from encrypted values before dropping column
        $certificates = DB::table('certificates')
            ->select('id', 'verification_code', 'encrypted_verification_code')
            ->whereNotNull('encrypted_verification_code')
            ->get();

        foreach ($certificates as $cert) {
            try {
                $decrypted = Crypt::decryptString($cert->encrypted_verification_code);
                DB::table('certificates')
                    ->where('id', $cert->id)
                    ->update(['verification_code' => $decrypted]);
            } catch (Throwable) {
                // If decryption key changed during rollback, preserve existing state
            }
        }

        if (Schema::hasColumn('certificates', 'encrypted_verification_code')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->dropColumn('encrypted_verification_code');
            });
        }
    }
};
