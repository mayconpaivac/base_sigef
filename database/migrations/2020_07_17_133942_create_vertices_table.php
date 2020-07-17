<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerticesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vertices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immobile_id');
            $table->string('vertice');
            $table->string('sigma_x');
            $table->string('sigma_y');
            $table->string('sigma_z');
            $table->string('indice');
            $table->string('este');
            $table->string('norte');
            $table->string('altura');

            $table->foreign('immobile_id')->references('id')->on('immobiles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vertices');
    }
}
