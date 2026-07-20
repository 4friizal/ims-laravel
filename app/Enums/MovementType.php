<?php

namespace App\Enums;

enum MovementType: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            MovementType::In => 'Masuk',
            MovementType::Out => 'Keluar',
        };
    }
}
