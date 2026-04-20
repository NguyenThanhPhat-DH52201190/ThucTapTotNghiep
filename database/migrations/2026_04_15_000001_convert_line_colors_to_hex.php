<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert color names to hex codes
        DB::table('mtp')->where('Line', 'Green')->update(['Line' => '#008000']);
        DB::table('mtp')->where('Line', 'Blue')->update(['Line' => '#0000FF']);
        DB::table('mtp')->where('Line', 'Orange')->update(['Line' => '#FFA500']);
        DB::table('mtp')->where('Line', 'Yellow')->update(['Line' => '#FFFF00']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert hex codes back to color names
        DB::table('mtp')->where('Line', '#008000')->update(['Line' => 'Green']);
        DB::table('mtp')->where('Line', '#0000FF')->update(['Line' => 'Blue']);
        DB::table('mtp')->where('Line', '#FFA500')->update(['Line' => 'Orange']);
        DB::table('mtp')->where('Line', '#FFFF00')->update(['Line' => 'Yellow']);
    }
};
