<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'price', 'is_active'])]
class Menu extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(RawMaterial::class, 'menu_materials')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function menuMaterials(): HasMany
    {
        return $this->hasMany(MenuMaterial::class);
    }
}
