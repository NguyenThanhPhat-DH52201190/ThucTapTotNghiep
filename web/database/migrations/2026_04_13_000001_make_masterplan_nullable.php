<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `mtp` MODIFY `Rdate` DATE NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `ETADate` DATE NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `ActDate` DATE NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `lt` INT NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `FirstOPT` DATE NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `mtp` MODIFY `Rdate` DATE NOT NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `ETADate` DATE NOT NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `ActDate` DATE NOT NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `lt` INT NOT NULL");
        DB::statement("ALTER TABLE `mtp` MODIFY `FirstOPT` DATE NOT NULL");
    }
};