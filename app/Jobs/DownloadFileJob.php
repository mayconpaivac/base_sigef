<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DownloadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    public $code;

    /**
     * Create a new job instance.
     *
     * @param string $code
     * @return void
     */
    public function __construct($code)
    {
        $this->code = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $url_parcela = 'https://sigef.incra.gov.br/geo/exportar/parcela/csv/' . $this->code . '/';
        $url_vertice = 'https://sigef.incra.gov.br/geo/exportar/vertice/csv/' . $this->code . '/';

        $response_parcela = file_get_contents($url_parcela);
        file_put_contents(storage_path('download/parcela_' . $this->code . '.csv'), $response_parcela);
        $response_vertice = file_get_contents($url_vertice);
        file_put_contents(storage_path('download/vertices_' . $this->code . '.csv'), $response_vertice);

        dispatch(new InsertFileJob($this->code));
    }
}
