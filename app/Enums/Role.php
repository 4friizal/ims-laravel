<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';

    public function label(): string
    {
        return match($this) {
            Role::Admin => 'Administrator',
            Role::Operator => 'Operator',
        };
    }

    public function canManageUsers(): bool
    {
        return $this === Role::Admin;
    }

    public function canManageInventory(): bool
    {
        return $this === Role::Admin || $this === Role::Operator;
    }
}
