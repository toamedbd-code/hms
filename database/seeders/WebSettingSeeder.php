<?php

namespace Database\Seeders;

use App\Models\WebSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class WebSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->datas() as $key => $value) {
            WebSetting::create($value);
        }
    }

    private function datas()
    {
        return [
            [
                'company_name' => 'Toamed', 
                'company_short_name' => 'TM', 
                'phone' => '+1-234-567-8901',
                'logo' => 'assets/toamed.png',
                'icon' => 'assets/toamed.png', 
                'report_title' => 'Toamed Official Report',
                'status' => 'Active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
        ];
    }
}
