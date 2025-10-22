<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('amazon_feeds', function (Blueprint $table) {
            $table->id();
            $table->string('feed_id')->nullable();
            $table->string('feed_type')->nullable();
            $table->string('status')->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('amazon_feeds');
    }
};
