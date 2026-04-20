<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add timestamps to revenue table
        if (!Schema::hasColumn('revenue', 'created_at')) {
            Schema::table('revenue', function ($table) {
                $table->timestamps();
            });
        }

        // Add timestamps to ocs table
        if (!Schema::hasColumn('ocs', 'created_at')) {
            Schema::table('ocs', function ($table) {
                $table->timestamps();
            });
        }

        // Convert revenue table to InnoDB
        DB::statement("ALTER TABLE `revenue` ENGINE=InnoDB");

        // Convert holidays table to InnoDB
        DB::statement("ALTER TABLE `holidays` ENGINE=InnoDB");
    }

    public function down(): void
    {
        // Remove timestamps from revenue
        if (Schema::hasColumn('revenue', 'created_at')) {
            Schema::table('revenue', function ($table) {
                $table->dropTimestamps();
            });
        }

        // Remove timestamps from ocs
        if (Schema::hasColumn('ocs', 'created_at')) {
            Schema::table('ocs', function ($table) {
                $table->dropTimestamps();
            });
        }

        // Convert back to MyISAM if needed
        DB::statement("ALTER TABLE `revenue` ENGINE=MyISAM");
        DB::statement("ALTER TABLE `holidays` ENGINE=MyISAM");
    }
};
