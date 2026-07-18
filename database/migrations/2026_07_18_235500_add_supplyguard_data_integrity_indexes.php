<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan constraint dan index untuk menjaga konsistensi data.
     */
    public function up(): void
    {
        /*
         * Bersihkan duplikasi lama dengan mempertahankan baris terbaru
         * sebelum unique constraint dipasang.
         */
        $this->removeDuplicates(
            'economic_indicators',
            ['country_id', 'year']
        );

        $this->removeDuplicates(
            'weather_cache',
            ['country_id']
        );

        $this->removeDuplicates(
            'currency_rates',
            ['country_id'],
            ['country_id']
        );

        $this->removeDuplicates(
            'currency_histories',
            ['base_currency', 'target_currency', 'rate_date']
        );

        $this->removeDuplicates(
            'risk_scores',
            ['country_id']
        );

        $this->removeDuplicates(
            'risk_histories',
            ['country_id', 'recorded_date']
        );

        $this->removeDuplicates(
            'watchlists',
            ['user_id', 'country_id']
        );

        Schema::table('economic_indicators', function (Blueprint $table): void {
            $table->unique(
                ['country_id', 'year'],
                'economic_country_year_unique'
            );
        });

        Schema::table('weather_cache', function (Blueprint $table): void {
            $table->unique(
                'country_id',
                'weather_country_unique'
            );
        });

        Schema::table('currency_rates', function (Blueprint $table): void {
            $table->unique(
                'country_id',
                'currency_rate_country_unique'
            );
        });

        Schema::table('currency_histories', function (Blueprint $table): void {
            $table->unique(
                ['base_currency', 'target_currency', 'rate_date'],
                'currency_history_pair_date_unique'
            );
        });

        Schema::table('risk_scores', function (Blueprint $table): void {
            $table->unique(
                'country_id',
                'risk_score_country_unique'
            );

            $table->index(
                ['risk_level', 'total_score'],
                'risk_level_score_index'
            );
        });

        Schema::table('risk_histories', function (Blueprint $table): void {
            $table->unique(
                ['country_id', 'recorded_date'],
                'risk_history_country_date_unique'
            );
        });

        Schema::table('watchlists', function (Blueprint $table): void {
            $table->unique(
                ['user_id', 'country_id'],
                'watchlist_user_country_unique'
            );
        });

        Schema::table('countries', function (Blueprint $table): void {
            $table->index(
                'region',
                'countries_region_index'
            );
        });

        Schema::table('ports', function (Blueprint $table): void {
            $table->index(
                ['country_id', 'type'],
                'ports_country_type_index'
            );

            $table->index(
                'port_code',
                'ports_code_index'
            );
        });

        Schema::table('news_cache', function (Blueprint $table): void {
            $table->index(
                ['country_id', 'sentiment', 'published_at'],
                'news_country_sentiment_date_index'
            );
        });

        Schema::table('api_logs', function (Blueprint $table): void {
            $table->index(
                ['api_name', 'status', 'requested_at'],
                'api_logs_filter_index'
            );
        });
    }

    /**
     * Menghapus index dan constraint tambahan.
     */
    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table): void {
            $table->dropIndex('api_logs_filter_index');
        });

        Schema::table('news_cache', function (Blueprint $table): void {
            $table->dropIndex('news_country_sentiment_date_index');
        });

        Schema::table('ports', function (Blueprint $table): void {
            $table->dropIndex('ports_country_type_index');
            $table->dropIndex('ports_code_index');
        });

        Schema::table('countries', function (Blueprint $table): void {
            $table->dropIndex('countries_region_index');
        });

        Schema::table('watchlists', function (Blueprint $table): void {
            $table->dropUnique('watchlist_user_country_unique');
        });

        Schema::table('risk_histories', function (Blueprint $table): void {
            $table->dropUnique('risk_history_country_date_unique');
        });

        Schema::table('risk_scores', function (Blueprint $table): void {
            $table->dropIndex('risk_level_score_index');
            $table->dropUnique('risk_score_country_unique');
        });

        Schema::table('currency_histories', function (Blueprint $table): void {
            $table->dropUnique('currency_history_pair_date_unique');
        });

        Schema::table('currency_rates', function (Blueprint $table): void {
            $table->dropUnique('currency_rate_country_unique');
        });

        Schema::table('weather_cache', function (Blueprint $table): void {
            $table->dropUnique('weather_country_unique');
        });

        Schema::table('economic_indicators', function (Blueprint $table): void {
            $table->dropUnique('economic_country_year_unique');
        });
    }

    /**
     * Mempertahankan ID terbaru untuk setiap kombinasi kolom unik.
     *
     * @param array<int, string> $groupColumns
     * @param array<int, string> $requiredNotNullColumns
     */
    private function removeDuplicates(
        string $table,
        array $groupColumns,
        array $requiredNotNullColumns = []
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table)
            ->select($groupColumns)
            ->selectRaw('MAX(id) as keep_id')
            ->groupBy($groupColumns)
            ->havingRaw('COUNT(*) > 1');

        foreach ($requiredNotNullColumns as $column) {
            $query->whereNotNull($column);
        }

        $duplicateGroups = $query->get();

        foreach ($duplicateGroups as $group) {
            $deleteQuery = DB::table($table);

            foreach ($groupColumns as $column) {
                $value = $group->{$column};

                if ($value === null) {
                    $deleteQuery->whereNull($column);
                } else {
                    $deleteQuery->where($column, $value);
                }
            }

            $deleteQuery
                ->where('id', '<>', $group->keep_id)
                ->delete();
        }
    }
};
