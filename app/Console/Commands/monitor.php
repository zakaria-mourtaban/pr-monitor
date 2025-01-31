<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\GithubController;

class monitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor prs of the repos listed in .repolist';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new GithubController();
        $controller->LoopThroughList();
    }
}
