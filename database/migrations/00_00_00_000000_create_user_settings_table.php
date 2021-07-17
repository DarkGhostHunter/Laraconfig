<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('user_settings', static function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('metadata_id');

            // You can change this morph column to suit your needs, like using `uuidMorphs()`.
            // $table->uuidMorphs('settable');
            $table->numericMorphs('settable');

            $table->string('value')->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
}