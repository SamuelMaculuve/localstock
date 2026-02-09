<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('content_analysis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->float('nsfw_score')->nullable();
            $table->float('violence_score')->nullable();
            $table->json('clip_embedding')->nullable();
            $table->unsignedBigInteger('duplicate_of')->nullable();
            $table->boolean('is_safe')->default(true);
            $table->timestamps();

            $table->foreign('file_id')->references('id')->on('file_managers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('content_analysis');
    }
};
