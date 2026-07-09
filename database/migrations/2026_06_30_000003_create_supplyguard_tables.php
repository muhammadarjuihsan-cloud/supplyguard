<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password');
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('official_name')->nullable();
            $table->string('cca2', 5)->nullable()->unique();
            $table->string('cca3', 5)->nullable()->unique();
            $table->string('capital')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('language')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('economic_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->year('year');
            $table->decimal('gdp', 20, 2)->nullable();
            $table->decimal('inflation', 8, 2)->nullable();
            $table->bigInteger('population')->nullable();
            $table->decimal('exports', 20, 2)->nullable();
            $table->decimal('imports', 20, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('weather_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->decimal('temperature', 8, 2)->nullable();
            $table->decimal('rainfall', 8, 2)->nullable();
            $table->decimal('wind_speed', 8, 2)->nullable();
            $table->string('weather_status')->nullable();
            $table->integer('weather_risk')->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('base_currency', 10)->default('USD');
            $table->string('target_currency', 10);
            $table->decimal('rate', 18, 6)->nullable();
            $table->decimal('change_percent', 8, 2)->nullable();
            $table->integer('currency_risk')->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('currency_histories', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 10)->default('USD');
            $table->string('target_currency', 10);
            $table->decimal('rate', 18, 6);
            $table->date('rate_date');
            $table->timestamps();
        });

        Schema::create('news_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source_name')->nullable();
            $table->string('url')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category')->nullable();
            $table->string('sentiment')->default('Neutral');
            $table->integer('positive_score')->default(0);
            $table->integer('negative_score')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('name');
            $table->string('country_name')->nullable();
            $table->string('port_code')->nullable();
            $table->string('type')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->integer('weather_score')->default(0);
            $table->integer('inflation_score')->default(0);
            $table->integer('currency_score')->default(0);
            $table->integer('news_score')->default(0);
            $table->integer('port_score')->default(0);
            $table->integer('total_score')->default(0);
            $table->string('risk_level')->default('Low');
            $table->text('recommendation')->nullable();
            $table->date('score_date')->nullable();
            $table->timestamps();
        });

        Schema::create('risk_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->integer('total_score')->default(0);
            $table->string('risk_level')->default('Low');
            $table->date('recorded_date');
            $table->timestamps();
        });

        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->string('category')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('positive_words', function (Blueprint $table) {
            $table->id();
            $table->string('word')->unique();
            $table->timestamps();
        });

        Schema::create('negative_words', function (Blueprint $table) {
            $table->id();
            $table->string('word')->unique();
            $table->timestamps();
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('api_name');
            $table->string('endpoint')->nullable();
            $table->string('status')->default('success');
            $table->integer('response_code')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('negative_words');
        Schema::dropIfExists('positive_words');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('watchlists');
        Schema::dropIfExists('risk_histories');
        Schema::dropIfExists('risk_scores');
        Schema::dropIfExists('ports');
        Schema::dropIfExists('news_cache');
        Schema::dropIfExists('currency_histories');
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('weather_cache');
        Schema::dropIfExists('economic_indicators');
        Schema::dropIfExists('countries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
