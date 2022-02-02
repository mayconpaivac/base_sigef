<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    public $code;

    /**
     * @var integer
     */
    public $timeout = 900;

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

        if (!Storage::disk(env('DISK'))->exists('download/parcela_' . $this->code . '.csv')) {
            $response = Http::withoutVerifying()
                ->withCookies([
                    'sessionid' => 'adf80e87e46fca3d8e7c83bb9d10ae73'
                ], 'sigef.incra.gov.br')
                ->withOptions([
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36',
                    ]
                ])
                ->get($url_parcela);

            if (!$response->clientError() && !$response->serverError()) {
                Storage::disk(env('DISK'))->put('download/parcela_' . $this->code . '.csv', $response->getBody());
            }
        }

        if (!Storage::disk(env('DISK'))->exists('download/vertices_' . $this->code . '.csv')) {
            $response = Http::withoutVerifying()
                ->withCookies([
                    'sessionid' => 'adf80e87e46fca3d8e7c83bb9d10ae73'
                ], 'sigef.incra.gov.br')
                ->withOptions([
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36',
                    ]
                ])
                ->get($url_vertice);

            if (!$response->clientError() && !$response->serverError()) {
                Storage::disk(env('DISK'))->put('download/vertices_' . $this->code . '.csv', $response->getBody());
            }
        }

        if (Storage::disk(env('DISK'))->exists('download/parcela_' . $this->code . '.csv') && Storage::disk(env('DISK'))->exists('download/vertices_' . $this->code . '.csv')) {
            dispatch(new InsertFileJob($this->code));
        } else {
            dispatch(new DownloadFileJob($this->code));
        }

        sleep(3);
    }
}
