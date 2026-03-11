<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique(); // Verknüpfung zu Qdrant 'source_file'
            $table->string('title');          // Originaler Dateiname oder umbenannter Titel
            $table->string('tag');            // z.B. 'professor' oder 'student'
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_documents');
    }
};
