<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 5)->default('MAD');
            $table->string('url')->nullable();
            $table->tinyInteger('discount_type')->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->string('brand_name')->nullable();
            $table->string('brand_logo')->nullable();
            $table->integer('stock')->default(0);
            $table->string('availability')->default('in stock');
            $table->string('google_product_category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'stock']);
            $table->index('slug');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
