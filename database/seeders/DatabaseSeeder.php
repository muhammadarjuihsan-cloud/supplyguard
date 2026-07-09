<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $tables = [
            'api_logs',
            'negative_words',
            'positive_words',
            'articles',
            'watchlists',
            'risk_histories',
            'risk_scores',
            'ports',
            'news_cache',
            'currency_histories',
            'currency_rates',
            'weather_cache',
            'economic_indicators',
            'countries',
            'users',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();

        DB::table('users')->insert([
            [
                'id' => 1,
                'name' => 'Admin SupplyGuard',
                'email' => 'admin@supplyguard.test',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'User Demo',
                'email' => 'user@supplyguard.test',
                'password' => Hash::make('password'),
                'role' => 'user',
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('countries')->insert([
            [
                'id' => 1,
                'name' => 'Germany',
                'official_name' => 'Federal Republic of Germany',
                'cca2' => 'DE',
                'cca3' => 'DEU',
                'capital' => 'Berlin',
                'region' => 'Europe',
                'subregion' => 'Western Europe',
                'currency_code' => 'EUR',
                'currency_name' => 'Euro',
                'language' => 'German',
                'latitude' => 51.1657,
                'longitude' => 10.4515,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'China',
                'official_name' => 'People\'s Republic of China',
                'cca2' => 'CN',
                'cca3' => 'CHN',
                'capital' => 'Beijing',
                'region' => 'Asia',
                'subregion' => 'Eastern Asia',
                'currency_code' => 'CNY',
                'currency_name' => 'Chinese Yuan',
                'language' => 'Chinese',
                'latitude' => 35.8617,
                'longitude' => 104.1954,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Indonesia',
                'official_name' => 'Republic of Indonesia',
                'cca2' => 'ID',
                'cca3' => 'IDN',
                'capital' => 'Jakarta',
                'region' => 'Asia',
                'subregion' => 'South-Eastern Asia',
                'currency_code' => 'IDR',
                'currency_name' => 'Indonesian Rupiah',
                'language' => 'Indonesian',
                'latitude' => -0.7893,
                'longitude' => 113.9213,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'Australia',
                'official_name' => 'Commonwealth of Australia',
                'cca2' => 'AU',
                'cca3' => 'AUS',
                'capital' => 'Canberra',
                'region' => 'Oceania',
                'subregion' => 'Australia and New Zealand',
                'currency_code' => 'AUD',
                'currency_name' => 'Australian Dollar',
                'language' => 'English',
                'latitude' => -25.2744,
                'longitude' => 133.7751,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('economic_indicators')->insert([
            ['country_id' => 1, 'year' => 2021, 'gdp' => 4250000000000, 'inflation' => 3.1, 'population' => 83100000, 'exports' => 1670000000000, 'imports' => 1460000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 1, 'year' => 2022, 'gdp' => 4080000000000, 'inflation' => 6.9, 'population' => 83200000, 'exports' => 1750000000000, 'imports' => 1570000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 1, 'year' => 2023, 'gdp' => 4520000000000, 'inflation' => 5.9, 'population' => 83400000, 'exports' => 1810000000000, 'imports' => 1590000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 1, 'year' => 2024, 'gdp' => 4590000000000, 'inflation' => 2.4, 'population' => 83500000, 'exports' => 1850000000000, 'imports' => 1600000000000, 'created_at' => $now, 'updated_at' => $now],

            ['country_id' => 2, 'year' => 2021, 'gdp' => 17700000000000, 'inflation' => 0.9, 'population' => 1412000000, 'exports' => 3360000000000, 'imports' => 2680000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'year' => 2022, 'gdp' => 17960000000000, 'inflation' => 2.0, 'population' => 1411000000, 'exports' => 3590000000000, 'imports' => 2720000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'year' => 2023, 'gdp' => 17880000000000, 'inflation' => 0.2, 'population' => 1410000000, 'exports' => 3380000000000, 'imports' => 2560000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'year' => 2024, 'gdp' => 18530000000000, 'inflation' => 0.5, 'population' => 1409000000, 'exports' => 3450000000000, 'imports' => 2630000000000, 'created_at' => $now, 'updated_at' => $now],

            ['country_id' => 3, 'year' => 2021, 'gdp' => 1186000000000, 'inflation' => 1.6, 'population' => 273800000, 'exports' => 231000000000, 'imports' => 196000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'year' => 2022, 'gdp' => 1319000000000, 'inflation' => 4.2, 'population' => 275500000, 'exports' => 291000000000, 'imports' => 237000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'year' => 2023, 'gdp' => 1371000000000, 'inflation' => 3.7, 'population' => 277500000, 'exports' => 258000000000, 'imports' => 221000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'year' => 2024, 'gdp' => 1429000000000, 'inflation' => 2.6, 'population' => 279000000, 'exports' => 265000000000, 'imports' => 230000000000, 'created_at' => $now, 'updated_at' => $now],

            ['country_id' => 4, 'year' => 2021, 'gdp' => 1550000000000, 'inflation' => 2.8, 'population' => 25600000, 'exports' => 342000000000, 'imports' => 261000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'year' => 2022, 'gdp' => 1690000000000, 'inflation' => 6.6, 'population' => 26000000, 'exports' => 401000000000, 'imports' => 309000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'year' => 2023, 'gdp' => 1723000000000, 'inflation' => 5.6, 'population' => 26600000, 'exports' => 370000000000, 'imports' => 302000000000, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'year' => 2024, 'gdp' => 1750000000000, 'inflation' => 3.8, 'population' => 27000000, 'exports' => 382000000000, 'imports' => 315000000000, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('weather_cache')->insert([
            ['country_id' => 1, 'temperature' => 16.5, 'rainfall' => 2.1, 'wind_speed' => 18.2, 'weather_status' => 'Light Rain', 'weather_risk' => 22, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'temperature' => 27.8, 'rainfall' => 5.4, 'wind_speed' => 28.5, 'weather_status' => 'Windy', 'weather_risk' => 45, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'temperature' => 30.4, 'rainfall' => 8.2, 'wind_speed' => 14.8, 'weather_status' => 'Heavy Rain', 'weather_risk' => 52, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'temperature' => 21.2, 'rainfall' => 1.0, 'wind_speed' => 12.3, 'weather_status' => 'Clear', 'weather_risk' => 15, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('currency_rates')->insert([
            ['country_id' => 1, 'base_currency' => 'USD', 'target_currency' => 'EUR', 'rate' => 0.920000, 'change_percent' => -0.40, 'currency_risk' => 20, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'base_currency' => 'USD', 'target_currency' => 'CNY', 'rate' => 7.250000, 'change_percent' => 0.80, 'currency_risk' => 34, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'base_currency' => 'USD', 'target_currency' => 'IDR', 'rate' => 16400.000000, 'change_percent' => 1.20, 'currency_risk' => 42, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'base_currency' => 'USD', 'target_currency' => 'AUD', 'rate' => 1.520000, 'change_percent' => -0.20, 'currency_risk' => 18, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('currency_histories')->insert([
            ['base_currency' => 'USD', 'target_currency' => 'EUR', 'rate' => 0.91, 'rate_date' => now()->subDays(4)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'EUR', 'rate' => 0.92, 'rate_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'EUR', 'rate' => 0.93, 'rate_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'EUR', 'rate' => 0.92, 'rate_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],

            ['base_currency' => 'USD', 'target_currency' => 'CNY', 'rate' => 7.18, 'rate_date' => now()->subDays(4)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'CNY', 'rate' => 7.20, 'rate_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'CNY', 'rate' => 7.23, 'rate_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'CNY', 'rate' => 7.25, 'rate_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],

            ['base_currency' => 'USD', 'target_currency' => 'IDR', 'rate' => 16100, 'rate_date' => now()->subDays(4)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'IDR', 'rate' => 16250, 'rate_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'IDR', 'rate' => 16300, 'rate_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'IDR', 'rate' => 16400, 'rate_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],

            ['base_currency' => 'USD', 'target_currency' => 'AUD', 'rate' => 1.50, 'rate_date' => now()->subDays(4)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'AUD', 'rate' => 1.51, 'rate_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'AUD', 'rate' => 1.53, 'rate_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['base_currency' => 'USD', 'target_currency' => 'AUD', 'rate' => 1.52, 'rate_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('news_cache')->insert([
            ['country_id' => 1, 'title' => 'Germany trade sector remains stable despite shipping delays', 'description' => 'Export activity improves while port congestion remains under control.', 'source_name' => 'SupplyGuard Demo News', 'url' => '#', 'category' => 'Trade', 'sentiment' => 'Positive', 'positive_score' => 3, 'negative_score' => 1, 'published_at' => now()->subDays(1), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'title' => 'China manufacturing growth faces logistics pressure', 'description' => 'Factory output increases but several routes report delay and congestion.', 'source_name' => 'SupplyGuard Demo News', 'url' => '#', 'category' => 'Logistics', 'sentiment' => 'Neutral', 'positive_score' => 2, 'negative_score' => 2, 'published_at' => now()->subDays(1), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'title' => 'Indonesia shipping routes affected by heavy rain', 'description' => 'Rain and delay may affect several port activities this week.', 'source_name' => 'SupplyGuard Demo News', 'url' => '#', 'category' => 'Shipping', 'sentiment' => 'Negative', 'positive_score' => 0, 'negative_score' => 3, 'published_at' => now()->subDays(2), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'title' => 'Australia export outlook improves with stable weather', 'description' => 'Stable weather and improved logistics support export activity.', 'source_name' => 'SupplyGuard Demo News', 'url' => '#', 'category' => 'Economy', 'sentiment' => 'Positive', 'positive_score' => 4, 'negative_score' => 0, 'published_at' => now()->subDays(2), 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('ports')->insert([
            ['country_id' => 1, 'name' => 'Port of Hamburg', 'country_name' => 'Germany', 'port_code' => 'DEHAM', 'type' => 'Seaport', 'latitude' => 53.5461, 'longitude' => 9.9661, 'description' => 'Major European logistics and container port.', 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'name' => 'Port of Shanghai', 'country_name' => 'China', 'port_code' => 'CNSHA', 'type' => 'Seaport', 'latitude' => 31.2304, 'longitude' => 121.4737, 'description' => 'One of the busiest container ports in the world.', 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'name' => 'Port of Tanjung Priok', 'country_name' => 'Indonesia', 'port_code' => 'IDTPP', 'type' => 'Seaport', 'latitude' => -6.1045, 'longitude' => 106.8808, 'description' => 'Main international port serving Jakarta and surrounding industries.', 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'name' => 'Port Botany', 'country_name' => 'Australia', 'port_code' => 'AUPBT', 'type' => 'Seaport', 'latitude' => -33.9667, 'longitude' => 151.2167, 'description' => 'Important container port in New South Wales.', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('risk_scores')->insert([
            ['country_id' => 1, 'weather_score' => 22, 'inflation_score' => 24, 'currency_score' => 20, 'news_score' => 15, 'port_score' => 10, 'total_score' => 21, 'risk_level' => 'Low', 'recommendation' => 'Germany is recommended for stable import planning.', 'score_date' => now()->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'weather_score' => 45, 'inflation_score' => 10, 'currency_score' => 34, 'news_score' => 35, 'port_score' => 20, 'total_score' => 32, 'risk_level' => 'Medium', 'recommendation' => 'China is usable but logistics and currency movement should be monitored.', 'score_date' => now()->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'weather_score' => 52, 'inflation_score' => 26, 'currency_score' => 42, 'news_score' => 55, 'port_score' => 30, 'total_score' => 43, 'risk_level' => 'Medium', 'recommendation' => 'Indonesia needs weather and shipping delay monitoring before import decisions.', 'score_date' => now()->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'weather_score' => 15, 'inflation_score' => 38, 'currency_score' => 18, 'news_score' => 10, 'port_score' => 12, 'total_score' => 20, 'risk_level' => 'Low', 'recommendation' => 'Australia is relatively stable for supply chain planning.', 'score_date' => now()->toDateString(), 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('risk_histories')->insert([
            ['country_id' => 1, 'total_score' => 25, 'risk_level' => 'Low', 'recorded_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 1, 'total_score' => 23, 'risk_level' => 'Low', 'recorded_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 1, 'total_score' => 21, 'risk_level' => 'Low', 'recorded_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],

            ['country_id' => 2, 'total_score' => 36, 'risk_level' => 'Medium', 'recorded_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'total_score' => 34, 'risk_level' => 'Medium', 'recorded_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 2, 'total_score' => 32, 'risk_level' => 'Medium', 'recorded_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],

            ['country_id' => 3, 'total_score' => 40, 'risk_level' => 'Medium', 'recorded_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'total_score' => 42, 'risk_level' => 'Medium', 'recorded_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 3, 'total_score' => 43, 'risk_level' => 'Medium', 'recorded_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],

            ['country_id' => 4, 'total_score' => 24, 'risk_level' => 'Low', 'recorded_date' => now()->subDays(3)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'total_score' => 22, 'risk_level' => 'Low', 'recorded_date' => now()->subDays(2)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
            ['country_id' => 4, 'total_score' => 20, 'risk_level' => 'Low', 'recorded_date' => now()->subDays(1)->toDateString(), 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('watchlists')->insert([
            ['user_id' => 2, 'country_id' => 1, 'note' => 'Stable supplier candidate', 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 2, 'country_id' => 3, 'note' => 'Monitor weather and port activity', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('positive_words')->insert([
            ['word' => 'growth', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'increase', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'profit', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'stable', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'improve', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'recovery', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'strong', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'safe', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('negative_words')->insert([
            ['word' => 'war', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'crisis', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'inflation', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'delay', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'disaster', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'congestion', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'strike', 'created_at' => $now, 'updated_at' => $now],
            ['word' => 'storm', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('articles')->insert([
            [
                'user_id' => 1,
                'title' => 'How SupplyGuard Calculates Global Supply Chain Risk',
                'slug' => 'how-supplyguard-calculates-risk',
                'content' => 'SupplyGuard combines weather risk, inflation risk, currency risk, news sentiment, and port availability to support supply chain decision making.',
                'category' => 'Risk Analysis',
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => 1,
                'title' => 'Why Port Monitoring Matters in International Trade',
                'slug' => 'why-port-monitoring-matters',
                'content' => 'Port location and port activity are important indicators because shipping delays can affect import cost and delivery time.',
                'category' => 'Logistics',
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
