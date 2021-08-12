<?php

namespace App\Jobs;

use App\Immobile;
use App\Vertice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Shapefile\Geometry\Linestring;
use Shapefile\Geometry\Point;
use Shapefile\Shapefile;
use Shapefile\ShapefileException;
use Shapefile\ShapefileWriter;

class CreateShapefileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var integer
     */
    public $timeout = 9999;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $start_time = now();

        $this->constructPolygons();
        $this->constructVertices();

        $memory_used = $this->formatBytes(memory_get_usage());
        $memory_used_max = $this->formatBytes(memory_get_peak_usage());
        $time = now()->diffInSeconds($start_time);

        logger()->info([
            'success' => true,
            'memory_usage' => $memory_used,
            'memory_usage_max' => $memory_used_max,
            'time' => $time . 's',
        ]);
    }

    /**
     * Generate shapefile from immobiles and store
     *
     * @return void
     */
    private function constructPolygons()
    {
        try {
            Storage::disk(env('DISK'))->delete([
                'public/parcelas.shp',
                'public/parcelas.dbf',
                'public/parcelas.shx',
            ]);

            $shape = new ShapefileWriter(storage_path('app/public/parcelas.shp'));
            $shape->setShapeType(Shapefile::SHAPE_TYPE_POLYLINE);

            $shape->addCharField('codigo');
            $shape->addCharField('imovel');

            Immobile::with([
                'vertices' => function ($query) {
                    $query->orderBy('indice');
                }
            ])
                ->cursor()
                ->each(function ($immobile) use ($shape) {
                    $points = null;
                    foreach ($immobile->vertices as $key => $vertice) {
                        $points[] = new Point($vertice->este, $vertice->norte);
                        if ($key + 1 === count($immobile->vertices)) {
                            $points[] = new Point($immobile->vertices[0]->este, $immobile->vertices[0]->norte);
                        }
                    }

                    $polyline = new Linestring($points);

                    $polyline->setData('codigo', $immobile->code);
                    $polyline->setData('imovel', utf8_decode(mb_convert_encoding(str_replace('â€“', '-', $immobile->immobile), 'windows-1252')));

                    $shape->writeRecord($polyline);

                    $polyline = null;
                    $points = null;
                });

            $shape = null;
        } catch (ShapefileException $e) {
            return 'Error Type: ' . $e->getErrorType()
            . "\nMessage: " . $e->getMessage()
            . "\nDetails: " . $e->getDetails();
        }
    }

    /**
     * Generate shapefile from all vertices not repeated
     *
     * @return void
     */
    private function constructVertices()
    {
        try {
            Storage::disk(env('DISK'))->delete([
                'public/vertices.cpg',
                'public/vertices.dbf',
                'public/vertices.dbt',
                'public/vertices.prj',
                'public/vertices.shp',
                'public/vertices.shx',
            ]);

            $shapefile = new ShapefileWriter(storage_path('app/public/vertices.shp'));

            $shapefile->setShapeType(Shapefile::SHAPE_TYPE_POINT);

            $shapefile->addCharField('vertice');
            $shapefile->addCharField('altura');
            $shapefile->addCharField('sigma_e');
            $shapefile->addCharField('sigma_n');
            $shapefile->addCharField('sigma_z');

            Vertice::select('vertice', 'este', 'norte', 'sigma_x', 'sigma_y', 'sigma_z', 'altura')
                ->distinct('vertice')
                ->groupBy('vertice', 'id')
                ->cursor()
                ->each(function ($vertice) use ($shapefile) {
                    $point = new Point($vertice->este, $vertice->norte);

                    $point->setData('vertice', $vertice->vertice);
                    $point->setData('altura', $vertice->altura);
                    $point->setData('sigma_e', $vertice->sigma_x);
                    $point->setData('sigma_n', $vertice->sigma_y);
                    $point->setData('sigma_z', $vertice->sigma_z);

                    $shapefile->writeRecord($point);
                });

            $shapefile = null;
        } catch (ShapefileException $e) {
            return 'Error Type: ' . $e->getErrorType()
            . "\nMessage: " . $e->getMessage()
            . "\nDetails: " . $e->getDetails();
        }
    }

    /**
     * Format bytes
     *
     * @param [type] $bytes
     * @param integer $precision
     * @return void
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
