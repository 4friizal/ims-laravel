<?php

namespace App\Enums;

enum MaterialUnit: string
{
    case Kilogram = 'kg';
    case Gram = 'gram';
    case Liter = 'liter';
    case Milliliter = 'ml';
    case Piece = 'pcs';

    public function label(): string
    {
        return match ($this) {
            MaterialUnit::Kilogram => 'Kilogram (kg)',
            MaterialUnit::Gram => 'Gram (g)',
            MaterialUnit::Liter => 'Liter (L)',
            MaterialUnit::Milliliter => 'Mililiter (ml)',
            MaterialUnit::Piece => 'Buah (pcs)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            MaterialUnit::Kilogram => 'kg',
            MaterialUnit::Gram => 'g',
            MaterialUnit::Liter => 'L',
            MaterialUnit::Milliliter => 'ml',
            MaterialUnit::Piece => 'pcs',
        };
    }
}
