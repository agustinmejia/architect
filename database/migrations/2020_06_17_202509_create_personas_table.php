<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apaterno');
            $table->string('amaterno');
            $table->string('ci');
            $table->string('numeroregistro', 20);
            $table->string('telefono', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->string('correo')->nullable();
            $table->date('fecha_afiliacion')->nullable();
            $table->string('ultimo_pago', 10)->nullable();
            $table->boolean('condicion')->default(1);
            $table->string('estado', 13)->default('ACTIVO');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('personas');
    }
}
