<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name_en' => 'Shareholder',
                'name_fa' => 'سهم دار',
                'name_ps' => 'سهم دار',
                'line_manager' => null
            ],
            [
                'name_en' => 'Head of Supervisors',
                'name_fa' => 'رئیس هیئت نظار',
                'name_ps' => 'رئیس هیئت نظار',
                'line_manager' => 1
            ],
            [
                'name_en' => 'Supervisor',
                'name_fa' => 'هیئت نظار',
                'name_ps' => 'هیئت نظار',
                'line_manager' => 2
            ],
            [
                'name_en' => 'Head of Operations',
                'name_fa' => 'رئیس عملیاتی',
                'name_ps' => 'رئیس عملیاتی',
                'line_manager' => 3
            ],
            [
                'name_en' => 'Operation Officer',
                'name_fa' => 'مسئول عملیاتی',
                'name_ps' => 'مسئول عملیاتی',
                'line_manager' => 3
            ],
            [
                'name_en' => 'Compliance Officer',
                'name_fa' => 'مسئول رعایت از قانون و مقررات',
                'name_ps' => 'مسئول رعایت از قانون و مقررات',
                'line_manager' => 3
            ],
            [
                'name_en' => 'IT Specialist',
                'name_fa' => 'مسئول تخنیکی',
                'name_ps' => 'مسئول تخنیکی',
                'line_manager' => 4
            ],
            [
                'name_en' => 'Signatory',
                'name_fa' => 'نماینده',
                'name_ps' => 'نماینده',
                'line_manager' => 4
            ],
            [
                'name_en' => 'Chef',
                'name_fa' => 'آشپز',
                'name_ps' => 'آشپز',
                'line_manager' => 4
            ]
        ];

        foreach ($data as $item) {
            \App\Models\Designation::create($item);
        }
    }
}
