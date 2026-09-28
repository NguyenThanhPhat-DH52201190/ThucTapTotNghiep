<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','warehouse','ppic','ie','prod','accountant','development') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::table('users')->where('role', 'development')->update(['role' => 'ie']);
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','warehouse','ppic','ie','prod','accountant') NOT NULL");
    }
};
