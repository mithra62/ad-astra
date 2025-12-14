<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Import;

class syncCraft extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-craft';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(Import $import): void
    {
        $import->run();
    }
}
