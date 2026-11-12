<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset')]
#[Description('Rebuild the demo account: products, links, history, forecasts and rules')]
class ResetDemo extends Command
{
    public function handle(): int
    {
        // Only the demo user's own data is rebuilt; other accounts are untouched.
        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
