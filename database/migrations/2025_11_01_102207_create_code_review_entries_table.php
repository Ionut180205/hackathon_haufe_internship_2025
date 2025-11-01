<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('code_review_entries', function (Blueprint $table) {
            $table->id();
            $table->string('file_name')->nullable();     // numele fișierului încărcat
            $table->string('mime_type')->nullable();     // tipul MIME
            $table->unsignedBigInteger('file_size')->nullable(); // dimensiunea în bytes
            $table->text('code');                        // conținutul codului
            $table->longText('review');                  // rezultatul analizei AI
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_review_entries');
    }
};
