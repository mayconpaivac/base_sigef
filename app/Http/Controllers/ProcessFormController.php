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

        if ($request->hasFile('file')) {
            $request->file('file')->move(storage_path('app'), 'file.html');
        } else {
            $response = Http::withoutVerifying()
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

        $file = file(storage_path('app/file.html'));

        $news = [];

        foreach ($file as $line) {
            preg_match_all("/\w{8}-\w{4}-\w{4}-\w{4}-\w{12}/", $line, $results);

            if (!empty($results[0])) {
                $uuid = array_unique($results[0])[0];
                if ($uuid === strtolower($uuid)) {
                    $news[$uuid] = $uuid;
                }
            }
        }

        array_shift($news);

        $immobiles = Immobile::cursor()->pluck('code');

        $olds = [];

        foreach ($immobiles as $immobile) {
            $olds[$immobile] = $immobile;
        }

        $download = array_diff_key($news, $olds);
        $exists = array_intersect_key($news, $olds);
        $delete = array_diff_key($olds, $news);

        if (count($download) > 0 || count($delete) > 0) {
            $bus = Bus::batch([])->name('Download files')->dispatch();
            foreach ($download as $job) {
                $bus->add(new DownloadFileJob(trim($job)));
            }

            foreach ($delete as $job) {
                $bus->add(new DeleteFileJob(trim($job)));
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
