<?php

namespace Database\Seeders;

use App\Models\Province;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = include(resource_path('data/provinces.php'));

        for ($i = 0; $i < count($provinces); $i++) {
            $record = $provinces[$i];
            Province::create([
                'id' => $record['id'],
                'name_fa' => $record['name_fa'],
                'name_pa' => $record['name_pa'],
                'name_en' => $record['name_en'],
                'country_id' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
    }
}
