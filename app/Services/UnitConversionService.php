<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitConversion;

class UnitConversionService
{
    /**
     * Convert quantity from one unit to another.
     * Returns converted quantity.
     */
    public function convert(float $quantity, int $fromUnitId, int $toUnitId): float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        // Direct conversion
        $direct = UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->first();

        if ($direct) {
            return $quantity * (float) $direct->multiplier;
        }

        // Inverse conversion (misal: jika kg -> g bernilai 1000, maka g -> kg adalah 1/1000)
        $inverse = UnitConversion::where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->first();

        if ($inverse && (float) $inverse->multiplier != 0) {
            return $quantity / (float) $inverse->multiplier;
        }

        $fromUnit = Unit::find($fromUnitId);
        $toUnit   = Unit::find($toUnitId);

        // Fallback untuk satuan berat standar jika belum tercatat di DB
        if ($fromUnit && $toUnit && $fromUnit->category === 'weight' && $toUnit->category === 'weight') {
            $fromKey = strtolower($fromUnit->symbol ?? $fromUnit->name);
            $toKey   = strtolower($toUnit->symbol ?? $toUnit->name);

            $grams = match ($fromKey) {
                'kg', 'kilogram' => $quantity * 1000,
                'g', 'gram'      => $quantity * 1,
                'mg', 'miligram' => $quantity * 0.001,
                default          => null,
            };

            if ($grams !== null) {
                return match ($toKey) {
                    'kg', 'kilogram' => $grams / 1000,
                    'g', 'gram'      => $grams / 1,
                    'mg', 'miligram' => $grams / 0.001,
                    default          => throw new \RuntimeException("Tidak dapat mengonversi {$fromUnit->name} ke {$toUnit->name}"),
                };
            }
        }

        throw new \RuntimeException(
            "Tidak ada konversi satuan dari {$fromUnit?->name} ({$fromUnit?->symbol}) ke {$toUnit?->name} ({$toUnit?->symbol})"
        );
    }

    /**
     * Check if two units belong to same category (e.g., both weight).
     */
    public function isSameCategory(int $unitId1, int $unitId2): bool
    {
        $unit1 = Unit::find($unitId1);
        $unit2 = Unit::find($unitId2);

        if (!$unit1 || !$unit2) {
            return false;
        }

        return $unit1->category === $unit2->category;
    }
}

