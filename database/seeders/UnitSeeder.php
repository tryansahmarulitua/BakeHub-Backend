<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $kg  = Unit::updateOrCreate(['name' => 'Kilogram'], ['symbol' => 'kg', 'category' => 'weight']);
        $g   = Unit::updateOrCreate(['name' => 'Gram'],     ['symbol' => 'g',  'category' => 'weight']);
        $mg  = Unit::updateOrCreate(['name' => 'Miligram'], ['symbol' => 'mg', 'category' => 'weight']);
        $pcs = Unit::updateOrCreate(['name' => 'Pcs'],      ['symbol' => 'pcs', 'category' => 'count']);
        $box = Unit::updateOrCreate(['name' => 'Box'],      ['symbol' => 'box', 'category' => 'count']);

        $conversions = [
            ['from' => $kg->id, 'to' => $g->id,  'multiplier' => 1000],
            ['from' => $g->id,  'to' => $kg->id, 'multiplier' => 0.001],
            ['from' => $g->id,  'to' => $mg->id, 'multiplier' => 1000],
            ['from' => $mg->id, 'to' => $g->id,  'multiplier' => 0.001],
            ['from' => $kg->id, 'to' => $mg->id, 'multiplier' => 1000000],
            ['from' => $mg->id, 'to' => $kg->id, 'multiplier' => 0.000001],
            ['from' => $box->id, 'to' => $pcs->id, 'multiplier' => 12],
            ['from' => $pcs->id, 'to' => $box->id, 'multiplier' => 1 / 12],
        ];

        foreach ($conversions as $c) {
            UnitConversion::updateOrCreate(
                ['from_unit_id' => $c['from'], 'to_unit_id' => $c['to']],
                ['multiplier' => $c['multiplier']]
            );
        }
    }
}
