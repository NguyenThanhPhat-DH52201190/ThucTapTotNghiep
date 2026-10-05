<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','warehouse','ppic','ie','prod','accountant','development','qa_qc') NOT NULL");
        }

        Schema::table('mtp', function (Blueprint $table) {
            $table->date('qa_inspection_date')->nullable();
            $table->string('qa_status', 20)->default('not_approved');
        });
    }

    public function down(): void
    {
        Schema::table('mtp', function (Blueprint $table) {
            $table->dropColumn(['qa_inspection_date', 'qa_status']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('users')->where('role', 'qa_qc')->update(['role' => 'ie']);
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','warehouse','ppic','ie','prod','accountant','development') NOT NULL");
        }
    }
};
