<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return match($this) {
            Role::Admin => 'Administrator',
            Role::Manager => 'Manager',
            Role::Staff => 'Staff',
        };
    }

    public function canManageUsers(): bool
    {
        return $this === Role::Admin;
    }

    public function canManageInventory(): bool
    {
        return $this === Role::Admin || $this === Role::Manager;
    }
}
