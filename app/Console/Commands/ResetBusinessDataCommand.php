<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetBusinessDataCommand extends Command
{
    protected $signature = 'db:reset-business-data
                            {--force : Run without the interactive confirmation}';

    protected $description = 'Delete business/test data while preserving users, colors, holidays, and migration history.';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('This command currently supports MySQL only.');

            return self::FAILURE;
        }

        $preservedTables = ['migrations', 'users', 'colors', 'holidays'];

        if (! $this->option('force') && ! $this->confirm(
            'This permanently deletes all data except users, colors, holidays, and migration history. Continue?'
        )) {
            $this->warn('Database reset cancelled.');

            return self::SUCCESS;
        }

        $tables = collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn (object $table) => array_values((array) $table)[0])
            ->reject(fn (string $table) => in_array($table, $preservedTables, true))
            ->values();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                $safeTable = str_replace('`', '``', $table);
                DB::statement("TRUNCATE TABLE `{$safeTable}`");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Business data reset completed.');
        $this->line('Preserved: users, colors, holidays, migrations.');

        return self::SUCCESS;
    }
}
