<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::table('users', function (Blueprint $table) use ($driver): void {
            if (Schema::hasColumn('users', 'password') && $driver !== 'sqlite') {
                $table->string('password')->nullable()->change();
            }

            if (! Schema::hasColumn('users', 'sso_id')) {
                $table->string('sso_id', 64)->nullable()->unique();
            }

            if (! Schema::hasColumn('users', 'sso_provider')) {
                $table->string('sso_provider', 30)->nullable();
            }

            if (! Schema::hasColumn('users', 'kyc_level')) {
                $table->string('kyc_level', 20)->nullable();
            }

            if (! Schema::hasColumn('users', 'camdigikey_id')) {
                $table->string('camdigikey_id', 100)->nullable()->unique();
            }

            if (! Schema::hasColumn('users', 'nbfs_id')) {
                $table->string('nbfs_id', 50)->nullable()->unique();
            }

            if (! Schema::hasColumn('users', 'last_sso_login_at')) {
                $table->timestamp('last_sso_login_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'nbfs_id')) {
                $table->dropUnique(['nbfs_id']);
                $table->dropColumn('nbfs_id');
            }

            if (Schema::hasColumn('users', 'camdigikey_id')) {
                $table->dropUnique(['camdigikey_id']);
                $table->dropColumn('camdigikey_id');
            }

            if (Schema::hasColumn('users', 'kyc_level')) {
                $table->dropColumn('kyc_level');
            }

            if (Schema::hasColumn('users', 'sso_provider')) {
                $table->dropColumn('sso_provider');
            }

            if (Schema::hasColumn('users', 'sso_id')) {
                $table->dropUnique(['sso_id']);
                $table->dropColumn('sso_id');
            }

            if (Schema::hasColumn('users', 'last_sso_login_at')) {
                $table->dropColumn('last_sso_login_at');
            }
        });
    }
};
