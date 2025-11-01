<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('code_review_entries', function (Blueprint $table) {
            // Adaugă doar dacă nu există deja (protejează rerulări)
            if (!Schema::hasColumn('code_review_entries', 'file_name')) {
                $table->string('file_name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('code_review_entries', 'mime_type')) {
                $table->string('mime_type')->nullable()->after('file_name');
            }
            if (!Schema::hasColumn('code_review_entries', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            }
            if (!Schema::hasColumn('code_review_entries', 'code')) {
                $table->text('code')->after('file_size');
            }
            if (!Schema::hasColumn('code_review_entries', 'review')) {
                $table->longText('review')->after('code');
            }
            if (!Schema::hasColumn('code_review_entries', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('code_review_entries', function (Blueprint $table) {
            // Reversibil (opțional): șterge coloanele adăugate
            if (Schema::hasColumn('code_review_entries', 'review')) {
                $table->dropColumn('review');
            }
            if (Schema::hasColumn('code_review_entries', 'code')) {
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('code_review_entries', 'file_size')) {
                $table->dropColumn('file_size');
            }
            if (Schema::hasColumn('code_review_entries', 'mime_type')) {
                $table->dropColumn('mime_type');
            }
            if (Schema::hasColumn('code_review_entries', 'file_name')) {
                $table->dropColumn('file_name');
            }
            // Timestamps (dacă au fost adăugate aici)
            if (Schema::hasColumn('code_review_entries', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
