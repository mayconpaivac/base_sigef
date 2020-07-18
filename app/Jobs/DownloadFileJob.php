<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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
        $this->code = preg_replace("/\r|\n/", '', $code);
        $this->onQueue('download');
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

        $arrContextOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];

        if (!Storage::exists('download/parcela_' . $this->code . '.csv')) {
            $response_parcela = file_get_contents($url_parcela, false, stream_context_create($arrContextOptions));
            Storage::put('download/parcela_' . $this->code . '.csv', $response_parcela);
        }

        if (!Storage::exists('download/vertices_' . $this->code . '.csv')) {
            $response_vertice = file_get_contents($url_vertice, false, stream_context_create($arrContextOptions));
            Storage::put('download/vertices_' . $this->code . '.csv', $response_vertice);
        }

        if (Storage::exists('download/parcela_' . $this->code . '.csv') or Storage::exists('download/vertices_' . $this->code . '.csv')) {
            dispatch(new InsertFileJob($this->code));
        } else {
            dispatch(new DownloadFileJob($this->code));
        }
    }
}
