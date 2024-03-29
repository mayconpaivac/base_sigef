<?php

namespace App\Jobs;

use App\Immobile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;

class DeleteFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * @var string
     */
    public $code;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $code)
    {
        $this->code = preg_replace("/\r|\n/", '', $code);
        $this->onQueue('delete');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Immobile::where('code', $this->code)->first()->delete();
    }
}
