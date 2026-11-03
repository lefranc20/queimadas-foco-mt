<?php

namespace App\Jobs;

use App\Services\ColetorClima;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportarClima implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [300, 900];

    public function __construct(public readonly int $dias = 7) {}

    public function handle(ColetorClima $coletor): void
    {
        $coletor->coletar($this->dias);
    }
}
