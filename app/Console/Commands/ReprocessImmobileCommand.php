<?php

namespace App\Console\Commands;

use App\Immobile;
use App\Jobs\DownloadFileJob;
use Illuminate\Console\Command;

class ReprocessImmobileCommand extends Command
{
    protected $signature = 'immobile:reprocess {code : O code UUID do imóvel}';

    protected $description = 'Remove os vértices de um imóvel e re-processa o download e inserção';

    public function handle()
    {
        $code = $this->argument('code');

        if (str_contains($code, ',') || str_contains($code, ";")) {
            $codes = preg_split("/[,;]/", $code);
        } else {
            $codes = [$code];
        }


        foreach ($codes as $code) {
            $immobile = Immobile::where('code', $code)->first();

            if (!$immobile) {
                $this->error("Imóvel não encontrado: {$code}");
                return 1;
            }

            $count = $immobile->vertices()->count();
            $invalid = $immobile->vertices()->get()->filter(function ($v) {
                return is_null($v->este) || is_null($v->norte) || !is_numeric($v->este) || !is_numeric($v->norte);
            });

            $this->info("Imóvel: {$immobile->immobile}");
            $this->info("Total de vértices: {$count}");
            $this->info("Vértices com coordenada inválida: {$invalid->count()}");

            if ($invalid->isNotEmpty()) {
                $this->table(['id', 'vertice', 'este', 'norte'], $invalid->map(fn($v) => [
                    $v->id, $v->vertice, $v->este, $v->norte,
                ])->toArray());
            }

            $immobile->vertices()->delete();
            $this->info("Vértices removidos.");

            dispatch(new DownloadFileJob($code))->onQueue('download');

            $this->info("Job de re-download despachado para: {$code} (InsertFileJob será disparado automaticamente pelo DownloadFileJob)");

        }
            return 0;
    }
}
