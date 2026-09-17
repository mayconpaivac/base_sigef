<?php

namespace App\Http\Controllers;

use App\Batch;
use App\Immobile;
use App\Jobs\DeleteFileJob;
use App\Jobs\DownloadFileJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessFormController extends Controller
{
    public function file(Request $request)
    {
        Storage::disk(env('DISK'))->delete('public/parcelas.zip');

        set_time_limit(300);


        if ($request->hasFile('file')) {
            $request->file('file')->move(storage_path('app'), 'file.html');
        } else {
            $response = Http::withoutVerifying()
                ->timeout(300)
                ->withCookies([
                    'sessionid' =>  config('app.session_id')
                ], 'sigef.incra.gov.br')
                ->withOptions([
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36',
                    ]
                ])
                ->get($request->post('link'));

            if (strstr($response->body(), 'https://sso.acesso.gov.br/authorize')) {
                throw new \Exception('Token do sigef "sessionid" expirado.');
            }

            file_put_contents(storage_path('app/file.html'), $response->body());
        }

        $handle = fopen(storage_path('app/file.html'), 'r');
        $news = [];

        if ($handle) {
            while (!feof($handle)) {
                $line = fgets($handle);
                if (preg_match("/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/", $line, $results)) {
                    $uuid = array_unique($results)[0];
                    $news[] = trim($uuid);
                }
            }
            fclose($handle);
        }

        $collectNews = collect($news)->except([0, 1])->values()->unique();

        $immobiles = Immobile::get()->pluck('code');

        $delete = $immobiles->diff($collectNews);
        $exists = $immobiles->intersect($collectNews);
        $download = $collectNews->diff($immobiles);

        if ($download->count()) {
            $bus = Bus::batch([])->name('Download files')->finally(function (\Illuminate\Bus\Batch $batch) {
                Batch::query()->where('batch_id', $batch->id)->update(['finished_at' => now()]);
            })->onQueue('download')->dispatch();

            foreach ($download->toArray() as $job) {
                $bus->add(new DownloadFileJob(trim($job)));
            }

            foreach ($delete->toArray() as $job) {
                dispatch(new DeleteFileJob(trim($job)))->onQueue('delete');
            }

            Batch::query()->create([
                'type' => 'download',
                'batch_id' => $bus->id,
            ]);
        }

        Storage::delete('file.html');

        return redirect('/')->with([
            'link' => $request->post('link'),
            'total_download' => count($download),
            'total_exists' => count($exists),
            'total_delete' => count($delete),
        ]);
    }
}
