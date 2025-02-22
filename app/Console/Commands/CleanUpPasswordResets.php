<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanUpPasswordResets extends Command
{
    protected $signature   = 'trwl:cleanUpPasswordResets';
    protected $description = 'Delete expired password reset tokens';

    public function handle(): int {
        $rowsAffected = DB::table('password_resets')
                          ->where('created_at', '<', Carbon::now()->subHour()->toIso8601String())
                          ->delete();

        echo strtr(':rowsAffected expired password reset tokens deleted', [':rowsAffected' => $rowsAffected]) . PHP_EOL;
        return 0;
    }
}
