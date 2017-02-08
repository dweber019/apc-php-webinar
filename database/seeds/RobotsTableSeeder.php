<?php

use Illuminate\Database\Seeder;

class RobotsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('robots')->insert([
          'name' => 'R2D2',
          'year' => '2016',
          'type' => 'droid',
          'created_at' => \Carbon\Carbon::now(),
          'updated_at' => \Carbon\Carbon::now()
        ]);

        DB::table('robots')->insert([
          'name' => 'B2-RP',
          'year' => '1999',
          'type' => 'mechanical',
          'created_at' => \Carbon\Carbon::now(),
          'updated_at' => \Carbon\Carbon::now()
        ]);

        DB::table('robots')->insert([
          'name' => 'E-XD',
          'year' => '2000',
          'type' => 'droid',
          'created_at' => \Carbon\Carbon::now(),
          'updated_at' => \Carbon\Carbon::now()
        ]);
    }
}
