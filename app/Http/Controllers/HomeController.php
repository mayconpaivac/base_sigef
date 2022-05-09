<?php

namespace App\Http\Controllers;

use App\Immobile;
use App\Jobs\CreateShapefileJob;
use App\Jobs\DeleteFileJob;
use App\Jobs\DownloadFileJob;
use App\Vertice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Shapefile\Shapefile;

class HomeController extends Controller
{
    /**
     * Show count from downloaded items
     *
     * @return void
     */
    public function index()
    {
        $immobiles = Immobile::count();
        $vertices = Vertice::count();

        return view('index', compact('immobiles', 'vertices'));
    }

    /**
     * Read the codes.txt file and determine which properties will be downloaded, updated and deleted
     *
     * @return void
     */
    public function init()
    {
        $lines = LazyCollection::make(function () {
            $handle = Storage::disk(env('DISK'))->readStream('codes.txt');

            while (($line = fgets($handle)) !== false) {
                yield preg_replace("/\r|\n/", '', $line);
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
                $x = explode('(', $line);
                $x = explode(')', $x[1])[0];
                $news[$x] = $x;
            }
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

        return response()->json([
            'exists' => count($exists),
            'download' => count($download),
            'delete' => count($delete),
        ]);
    }

    /**
     * Generate shapefile from all data downloaded
     *
     * @return void
     */
    public function shape()
    {
        dispatch(new CreateShapefileJob());

        return back();
    }
}
