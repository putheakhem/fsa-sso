<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'fsa_sso_access_token')) {
                $table->text('fsa_sso_access_token')->nullable();
            }

            if (! Schema::hasColumn('users', 'fsa_sso_token_expires_at')) {
                $table->timestamp('fsa_sso_token_expires_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'fsa_sso_token_client_code')) {
                $table->string('fsa_sso_token_client_code', 100)->nullable();
            }

            if (! Schema::hasColumn('users', 'fsa_sso_token_last_used_at')) {
                $table->timestamp('fsa_sso_token_last_used_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'fsa_sso_token_last_used_at')) {
                $table->dropColumn('fsa_sso_token_last_used_at');
            }

            if (Schema::hasColumn('users', 'fsa_sso_token_client_code')) {
                $table->dropColumn('fsa_sso_token_client_code');
            }

            if (Schema::hasColumn('users', 'fsa_sso_token_expires_at')) {
                $table->dropColumn('fsa_sso_token_expires_at');
            }

            if (Schema::hasColumn('users', 'fsa_sso_access_token')) {
                $table->dropColumn('fsa_sso_access_token');
            }
        });
    }
};
