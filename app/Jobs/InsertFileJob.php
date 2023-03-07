<?php

namespace App\Jobs;

use App\Immobile;
use App\Support\Decimal;
use App\Support\GPointConverter;
use App\Vertice;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

class InsertFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Batchable;

    /**
     * @var string
     */
    public $code;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code)
    {
        $this->code = preg_replace("/\r|\n/", '', $code);
        $this->onQueue('insert');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if (!Storage::disk(env('DISK'))->exists('download/parcela_' . $this->code . '.csv') or !Storage::disk(env('DISK'))->exists('download/vertices_' . $this->code . '.csv')) {
                dispatch(new DownloadFileJob($this->code));
                return true;
            }

            DB::beginTransaction();
            $csv = LazyCollection::make(function () {
                $handle = Storage::disk(env('DISK'))->readStream('download/parcela_' . $this->code . '.csv');

                while (($line = fgetcsv($handle, 0, ';')) !== false) {
                    yield $line;
                }
            });

            foreach ($csv as $key => $line) {
                if ($key === 1) {
                    $data['code'] = $line[0];
                    $data['immobile'] = str_replace("\n", "", $line[1]) ?: '-';

                    if (!$immobile = Immobile::where('code', $data['code'])->first()) {
                        $immobile = Immobile::create($data);
                    }
                }
            }

            $csv = LazyCollection::make(function () {
                $handle = Storage::disk(env('DISK'))->readStream('download/vertices_' . $this->code . '.csv');

                while (($line = fgetcsv($handle, 0, ';')) !== false) {
                    yield $line;
                }
            });

            foreach ($csv as $key => $line) {
                if ($key !== 0) {
                    $pointXY = $line[12];
                    preg_match_all("/[-+]?[0-9]\d*(\.\d+)/", $pointXY, $result);
                    $longitude = $result[0][0];
                    $latitude = $result[0][1];

                    $gpoint = new GPointConverter('WGS 84');
                    $gpoint = $gpoint->convertLatLngToUtm($latitude, $longitude);
                    $este = $gpoint[0];
                    $norte = $gpoint[1];
                    $zone = $gpoint[2];

                    if ($zone !== '20L') {
                        $gpoint = new GPointConverter('WGS 84');
                        [$lat, $lng] = $gpoint->convertUtmToLatLng($este, $norte, $zone);
                        $gpoint = new GPointConverter('WGS 84');
                        $gpoint = $gpoint->convertLatLngToUtm($lat, $lng, 20);
                        $este = $gpoint[0];
                        $norte = $gpoint[1];
                    }

                    $data['vertices'][$key - 1]['immobile_id'] = $immobile->id;
                    $data['vertices'][$key - 1]['vertice'] = $line[1];
                    $data['vertices'][$key - 1]['sigma_x'] = str_replace(',', '.', $line[4]);
                    $data['vertices'][$key - 1]['sigma_y'] = str_replace(',', '.', $line[5]);
                    $data['vertices'][$key - 1]['sigma_z'] = str_replace(',', '.', $line[6]);
                    $data['vertices'][$key - 1]['indice'] = $line[8];
                    $data['vertices'][$key - 1]['este'] = $este;
                    $data['vertices'][$key - 1]['norte'] = $norte;
                    $data['vertices'][$key - 1]['altura'] = str_replace(',', '.', $line[11]);
                }
            }

            if (!Vertice::where('immobile_id', $immobile->id)->count() > 0) {
                foreach (array_chunk($data['vertices'], 1000) as $vertice) {
                    $immobile->vertices()->insert($vertice);
                }
            }

            Storage::disk(env('DISK'))->delete('download/parcela_' . $this->code . '.csv');
            Storage::disk(env('DISK'))->delete('download/vertices_' . $this->code . '.csv');

            DB::commit();
        } catch (\Exception $e) {
            logger()->error($this->code);
            logger()->error($e);
            DB::rollBack();
        }
    }
}
