<?php

namespace App\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Batchable;

    /**
     * @var integer
     */
    public $timeout = 900;

    public function __construct(protected string $code)
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

        echo 'https://sigef.incra.gov.br/geo/parcela/detalhe/' . $this->code . '/' . PHP_EOL;

        if (
            !Storage::disk(env('DISK'))->exists('download/parcela_' . $this->code . '.csv')
            || str_contains(Storage::disk(env('DISK'))->get('download/parcela_' . $this->code . '.csv'), 'The requested URL was rejected.')
        ) {
            $response = Http::withoutVerifying()
                ->withCookies([
                    'sessionid' => config('app.session_id'),
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

        if (
            !Storage::disk(env('DISK'))->exists('download/vertices_' . $this->code . '.csv')
            || str_contains(Storage::disk(env('DISK'))->get('download/vertices_' . $this->code . '.csv'), 'The requested URL was rejected.')
        ) {
            $response = Http::withoutVerifying()
                ->withCookies([
                    'sessionid' => config('app.session_id'),
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

        if (
            (Storage::disk(env('DISK'))->exists('download/parcela_' . $this->code . '.csv') && Storage::disk(env('DISK'))->exists('download/vertices_' . $this->code . '.csv'))
            && (!str_contains(Storage::disk(env('DISK'))->get('download/parcela_' . $this->code . '.csv'), 'The requested URL was rejected.')
            && !str_contains(Storage::disk(env('DISK'))->get('download/vertices_' . $this->code . '.csv'), 'The requested URL was rejected.'))
        ) {
            dispatch(new InsertFileJob($this->code))->onQueue('insert');
        } else {
            $this->batch()->add(new DownloadFileJob($this->code));
        }

        echo 'Download: ' . $this->code . PHP_EOL;

        sleep(1);
    }
}
