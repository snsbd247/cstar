<?php

namespace App\Console\Commands;

use App\Services\GoLiveChecks;
use Illuminate\Console\Command;

/** Prints the go-live checklist (the same list as Settings → System). Exit code 1 while anything is open. */
class GoLiveCheckCommand extends Command
{
    protected $signature = 'cstar:go-live-check';

    protected $description = 'Show what is still missing before C-STAR goes live';

    public function handle(GoLiveChecks $checks): int
    {
        $all = collect($checks->all());
        foreach (['server' => 'Server', 'data' => 'Center data'] as $group => $title) {
            $this->line("<options=bold>{$title}</>");
            foreach ($all->where('group', $group) as $check) {
                $this->line($check['ok'] ? "  <fg=green>✔</> {$check['label']}" : "  <fg=yellow>…</> {$check['label']} — {$check['hint']}");
            }
        }
        $open = $all->where('ok', false)->count();
        $open ? $this->warn("{$open} item(s) still open.") : $this->info('Ready to go live.');

        return $open ? self::FAILURE : self::SUCCESS;
    }
}
