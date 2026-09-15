<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->after('user_id');
            $table->string('phone')->nullable()->after('recipient_name');

            $table->string('city')->nullable()->after('phone');
            $table->string('neighborhood')->nullable()->after('city');

            $table->text('address')->nullable()->after('neighborhood');
            $table->text('address_details')->nullable()->after('address');

            $table->decimal('latitude', 10, 7)->nullable()->after('address_details');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->text('delivery_note')->nullable()->after('longitude');

            $table->date('delivery_date')->nullable()->after('delivery_note');
            $table->time('delivery_time')->nullable()->after('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'recipient_name',
                'phone',
                'city',
                'neighborhood',
                'address',
                'address_details',
                'latitude',
                'longitude',
                'delivery_note',
                'delivery_date',
                'delivery_time',
            ]);
        });
    }
};