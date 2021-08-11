<?php

namespace App\Jobs;

use App\Immobile;
use App\Support\Decimal;
use App\Support\GPointConverter;
use App\Vertice;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
                if ($key !== 0) {
                    $data['code'] = $line[0];
                    $data['immobile'] = $line[1];

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
                    $este = str_replace(',', '.', $line[9]);
                    $norte = str_replace(',', '.', $line[10]);

                    if (strstr(mb_strtoupper($este), 'W')) {
                        $decimal = new Decimal();
                        $x = explode(' ', str_replace('W', '', mb_strtoupper($este)));
                        $deg = $x[0];
                        $min = $x[1];
                        $sec = str_replace(',', '.', $x[2]);

                        $longitude = '-' . $decimal->DMStoDEC($deg, $min, $sec);

                        $x = explode(' ', str_replace('S', '', mb_strtoupper($norte)));
                        $deg = $x[0];
                        $min = $x[1];
                        $sec = str_replace(',', '.', $x[2]);

                        $latitude = '-' . $decimal->DMStoDEC($deg, $min, $sec);

                        $gpoint = new GPointConverter('WGS 84');
                        $gpoint = $gpoint->convertLatLngToUtm($latitude, $longitude);
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

            DB::commit();

            Storage::disk(env('DISK'))->delete('download/parcela_' . $this->code . '.csv');
            Storage::disk(env('DISK'))->delete('download/vertices_' . $this->code . '.csv');
        } catch (\Exception $e) {
            logger()->error($this->code);
            logger()->error($e);
        }
    }
}
