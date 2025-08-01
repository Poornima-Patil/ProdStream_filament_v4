<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PartNumbersTableSeeder extends Seeder
{
    public function run(): void
    {
        $factoryId = env('SEED_FACTORY_ID', 1); 
        $revisions = ['0', 'A', 'B', 'C'];
        $now = Carbon::now();

        $componentTypes = [
            'Control Panel Housing', 'Gear Assembly', 'Sensor Module', 'Valve Body',
            'Drive Shaft', 'Brake Pad Set', 'Pump Casing', 'Motor Base Plate',
            'Cooling Fan Blade', 'Exhaust Manifold', 'Transmission Cover', 'Bearing Block',
            'Hydraulic Cylinder', 'Timing Chain', 'Pressure Regulator', 'Compressor Head',
            'Fuel Injector', 'Ignition Coil Bracket', 'Air Filter Housing', 'Clutch Disk',
            'Axle Tube', 'Tension Pulley', 'Actuator Arm', 'Gearbox Mount', 'Sealing Gasket'
        ];

        foreach ($componentTypes as $index => $component) {
            $partNumber = (string) random_int(1000000000, 9999999999);

            foreach ($revisions as $revision) {
                DB::table('part_numbers')->insert([
                    'partnumber'   => $partNumber,
                    'revision'     => $revision,
                    'description'  => "{$component} - Rev {$revision}",
                    'factory_id'   => $factoryId,
                    'cycle_time'   => random_int(30, 300), // e.g., 30s to 5 mins
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }
}
