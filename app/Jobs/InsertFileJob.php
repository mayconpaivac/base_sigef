<?php

namespace App\Jobs;

use App\Immobile;
use App\Support\Decimal;
use App\Support\GPointConverter;
use App\Vertice;
use Illuminate\Bus\Batch;
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

    public function __construct(protected string $code)
    {
        $this->code = trim(preg_replace("/\r|\n/", '', $code));
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
            if (
                (!Storage::disk(env('DISK'))->exists('download/parcela_' . $this->code . '.csv')
                or !Storage::disk(env('DISK'))->exists('download/vertices_' . $this->code . '.csv'))
                || (str_contains(Storage::disk(env('DISK'))->get('download/parcela_' . $this->code . '.csv'), 'The requested URL was rejected.')
                or str_contains(Storage::disk(env('DISK'))->get('download/vertices_' . $this->code . '.csv'), 'The requested URL was rejected.'))
            ) {
                $this->batch()->add(new DownloadFileJob($this->code));
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
                    $gpoint = new GPointConverter('WGS 84');

                    if (str_contains($line[9], 'W') or str_contains($line[10], 'S')) {
                        // Convert DMS to Decimal
                        // "60 13 33,761 W" or "7 59 57,492 S" (degree not always zero-padded)
                        $long = preg_split('/\s+/', trim($line[9]));
                        $longDeg = $long[0];
                        $longMin = $long[1];
                        $longSec = str_replace(',', '.', $long[2]);

                        $lat = preg_split('/\s+/', trim($line[10]));
                        $latDeg = $lat[0];
                        $latMin = $lat[1];
                        $latSec = str_replace(',', '.', $lat[2]);

                        $decimal = new Decimal();

                        $latitude = $decimal->DMStoDEC((float) $latDeg, (float) $latMin, (float) $latSec);
                        $longitude = $decimal->DMStoDEC((float) $longDeg, (float) $longMin, (float) $longSec);

                        $result = $gpoint->convertLatLngToUtm(-1 * $latitude, -1 * $longitude, 20);
                    } else {
                        // X/Y columns are already projected UTM coordinates (este/norte), not lat/lng
                        $result = [str_replace(',', '.', $line[9]), str_replace(',', '.', $line[10])];
                    }

                    $este = $result[0];
                    $norte = $result[1];

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

            echo 'Inserted ' . $this->code . PHP_EOL;

            DB::commit();
        } catch (\Exception $e) {
            logger()->error($this->code);
            logger()->error($e);
            DB::rollBack();
        }
    }
}
