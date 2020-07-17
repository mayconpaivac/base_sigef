<?php

namespace App\Http\Controllers;

use App\Immobile;
use App\Jobs\DeleteFileJob;
use App\Jobs\DownloadFileJob;
use Illuminate\Support\LazyCollection;

class HomeController extends Controller
{
    public function index()
    {
        return 'ola';
    }

    public function init()
    {
        $lines = LazyCollection::make(function () {
            $handle = fopen(storage_path('app/codes.txt'), 'r');

            while (($line = fgets($handle)) !== false) {
                yield $line;
            }
        });

        $immobiles = Immobile::cursor()->pluck('code');

        $olds = [];
        $news = [];

        foreach ($immobiles as $immobile) {
            $olds[$immobile] = $immobile;
        }

        foreach ($lines as $line) {
            $line = str_replace("\n", '', $line);
            if ($line) {
                $news[$line] = $line;
            }
        }

        $donwload = array_diff_key($news, $olds);
        $exists = array_intersect_key($news, $olds);
        $delete = array_diff_key($olds, $news);

        foreach ($donwload as $job) {
            dispatch(new DownloadFileJob($job));
        }

        foreach ($delete as $job) {
            dispatch(new DeleteFileJob($job));
        }

        return [
            'exists' => count($exists),
            'donwload' => count($donwload),
            'delete' => count($delete),
        ];
    }
}
