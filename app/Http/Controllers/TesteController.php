<?php

namespace App\Http\Controllers;

use App\Immobile;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Shapefile\ShapefileReader;

class TesteController extends Controller
{
    public function index()
    {
        try {
            $shape = new ShapefileReader(storage_path('app/aff/vertices.shp'));
            // Read all the records
            $i = 0;
            while ($geometry = $shape->fetchRecord()) {
                // Skip the record if marked as "deleted"
                if ($geometry->isDeleted()) {
                    continue;
                }

                // Print Geometry as an Array
                // print_r($geometry->getArray());
                $data[$i]['code'] = $geometry->getDataArray()['QRCODE'];
                $data[$i]['este'] = $geometry->getArray()['x'];
                $data[$i]['norte'] = $geometry->getArray()['y'];
                $data[$i]['vertice'] = $geometry->getDataArray()['VERTICE'];
                $data[$i]['sigma_x'] = $geometry->getDataArray()['SIGMA_X'];
                $data[$i]['sigma_y'] = $geometry->getDataArray()['SIGMA_Y'];
                $data[$i]['sigma_z'] = $geometry->getDataArray()['SIGMA_Z'];

                // Print DBF data
                // print_r($geometry->getDataArray());

                $i++;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }

        $unique_codes = collect($data)->unique('code')->pluck('code');

        $data_collect = collect($data);

        foreach ($unique_codes as $code) {
            $csv = LazyCollection::make(function () use ($code) {
                $handle = Storage::disk(env('DISK'))->readStream('13/' . $code . '_v.csv');

                while (($line = fgetcsv($handle, 0, ';')) !== false) {
                    yield $line;
                }
            });

            $immobile = Immobile::where('code', $code)->first();
            $vertices = [];

            foreach ($csv as $key => $line) {
                if ($key !== 0) {
                    $datac = $data_collect->where('vertice', $line[1])->first();

                    $vertices[$key - 1]['immobile_id'] = $immobile->id;
                    $vertices[$key - 1]['vertice'] = $line[1];
                    $vertices[$key - 1]['sigma_x'] = str_replace(',', '.', $line[4]);
                    $vertices[$key - 1]['sigma_y'] = str_replace(',', '.', $line[5]);
                    $vertices[$key - 1]['sigma_z'] = str_replace(',', '.', $line[6]);
                    $vertices[$key - 1]['indice'] = $line[8];
                    $vertices[$key - 1]['este'] = $datac['este'];
                    $vertices[$key - 1]['norte'] = $datac['norte'];
                    $vertices[$key - 1]['altura'] = str_replace(',', '.', $line[11]);
                }
            }
            $immobile->vertices()->insert($vertices);
        }

        return $unique_codes;
    }

    public function aff()
    {
        try {
            $shape = new ShapefileReader(storage_path('app/aff/vertices.shp'));
            // Read all the records
            $i = 0;
            while ($geometry = $shape->fetchRecord()) {
                // Skip the record if marked as "deleted"
                if ($geometry->isDeleted()) {
                    continue;
                }

                // Print Geometry as an Array
                // print_r($geometry->getArray());
                $data[$i]['code'] = $geometry->getDataArray()['QRCODE'];
                $data[$i]['este'] = $geometry->getArray()['x'];
                $data[$i]['norte'] = $geometry->getArray()['y'];
                $data[$i]['vertice'] = $geometry->getDataArray()['VERTICE'];
                $data[$i]['sigma_x'] = $geometry->getDataArray()['SIGMA_X'];
                $data[$i]['sigma_y'] = $geometry->getDataArray()['SIGMA_Y'];
                $data[$i]['sigma_z'] = $geometry->getDataArray()['SIGMA_Z'];

                // Print DBF data
                // print_r($geometry->getDataArray());

                $i++;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }

        $unique_codes = collect($data)->unique('code')->pluck('code');

        $data_collect = collect($data);

        foreach ($unique_codes as $code) {
            // Immobile::where('code', $code)->first()->vertices()->delete();
            $aff[] = Immobile::withCount('vertices')->where('code', $code)->first();
        }

        return $aff;
    }
}
