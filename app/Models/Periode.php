<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'periode',
    'statut',
    'cloture_le',
])]
class Periode extends Model
{
    public const STATUT_OUVERTE = 'OUVERTE';
    public const STATUT_CLOTURE = 'CLOTURE';

    public const STATUTS = [
        self::STATUT_OUVERTE,
        self::STATUT_CLOTURE,
    ];

    protected function casts(): array
    {
        return [
            'cloture_le' => 'datetime',
        ];
    }

    public function scopeOuvertes(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_OUVERTE);
    }

    public function scopeCloturees(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_CLOTURE);
    }

    public function estOuverte(): bool
    {
        return $this->statut === self::STATUT_OUVERTE;
    }

    public function estCloturee(): bool
    {
        return $this->statut === self::STATUT_CLOTURE;
    }

    public function cloturer(): bool
    {
        return $this->update([
            'statut'     => self::STATUT_CLOTURE,
            'cloture_le' => now(),
        ]);
    }

    public function rouvrir(): bool
    {
        return $this->update([
            'statut'     => self::STATUT_OUVERTE,
            'cloture_le' => null,
        ]);
    }

    public static function pourDate(\DateTimeInterface $date): ?self
    {
        return static::where('periode', $date->format('Y-m'))->first();
    }
}
