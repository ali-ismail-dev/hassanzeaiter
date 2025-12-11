<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('external_id')->index();
            $table->string('name');
            $table->string('label');
            $table->enum('field_type', ['text', 'textarea', 'number', 'select', 'radio', 'checkbox', 'date', 'email', 'url']);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->integer('order')->default(0);
            $table->string('validation_rules')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['category_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_fields');
    }
};
