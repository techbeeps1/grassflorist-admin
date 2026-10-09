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
        Schema::create('delivery_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->string('title_en')->default('Holiday / Maintenance');
            $table->string('title_ar')->default('عطلة / صيانة');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('reason_en')->nullable();
            $table->text('reason_ar')->nullable();
            $table->foreignId('delivery_slot_id')->nullable()->constrained('delivery_slots')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_blocked_dates');
    }
};
