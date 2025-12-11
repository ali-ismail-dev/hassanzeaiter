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
         Schema::create('ad_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_field_id')->constrained()->onDelete('cascade');
            
            // Polymorphic value storage - only ONE of these will be used per row
            $table->text('value_text')->nullable();
            $table->integer('value_integer')->nullable();
            $table->decimal('value_decimal', 12, 2)->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->json('value_json')->nullable(); // For multiple selections (checkboxes)
            
            // For select/radio - store the selected option ID
            $table->foreignId('category_field_option_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
            
            // Ensure one value per ad per field
            $table->unique(['ad_id', 'category_field_id']);
            $table->index(['ad_id', 'category_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_field_values');
    }
};
