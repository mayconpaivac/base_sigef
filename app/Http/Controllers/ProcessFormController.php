<?php

namespace App\Http\Controllers;

use App\Immobile;
use App\Jobs\DeleteFileJob;
use App\Jobs\DownloadFileJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessFormController extends Controller
{
    public function file(Request $request)
    {
        if ($request->hasFile('file')) {
            $request->file('file')->move(storage_path('app'), 'file.html');
        } else {
            $response = Http::withoutVerifying()
                ->withCookies([
                    'sessionid' => '8e0350b2bf8e04d5db48957f97b6b0e2'
                ], 'sigef.incra.gov.br')
                ->withOptions([
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.89 Safari/537.36',
                    ]
                ])
                ->get($request->post('link'));

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

        foreach ($download as $job) {
            dispatch(new DownloadFileJob(trim($job)));
        }

        foreach ($delete as $job) {
            dispatch(new DeleteFileJob(trim($job)));
        }

        Storage::delete('file.html');

        return back()->with([
            'total_download' => count($download),
            'total_exists' => count($exists),
            'total_delete' => count($delete),
        ]);
    }
}
