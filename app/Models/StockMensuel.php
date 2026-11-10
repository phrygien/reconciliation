<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'periode',
    'code_stock',
    'parkod',
    'qte_ouverture',
    'val_ouverture',
    'qte_in',
    'val_in',
    'qte_out',
    'val_out',
    'qte_stk',
    'val_stk',
    'qte_cloture',
    'val_cloture',
    'pamp_cloture',
])]
class StockMensuel extends Model
{
    protected $table = 'stock_mensuels';

    protected function casts(): array
    {
        return [
            'qte_ouverture' => 'integer',
            'val_ouverture' => 'decimal:4',
            'qte_in'        => 'integer',
            'val_in'        => 'decimal:4',
            'qte_out'       => 'integer',
            'val_out'       => 'decimal:4',
            'qte_stk'       => 'integer',
            'val_stk'       => 'decimal:4',
            'qte_cloture'   => 'integer',
            'val_cloture'   => 'decimal:4',
            'pamp_cloture'  => 'decimal:4',
        ];
    }

    public function periodeRef(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode', 'periode');
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class, 'code_stock', 'code_stock');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'parkod', 'parkod');
    }

    public function scopePourPeriode(Builder $query, string $periode): Builder
    {
        return $query->where('periode', $periode);
    }

    public function scopePourSociete(Builder $query, string $codeStock): Builder
    {
        return $query->where('code_stock', $codeStock);
    }

    public function scopePourArticle(Builder $query, string $parkod): Builder
    {
        return $query->where('parkod', $parkod);
    }

    /**
     * Clôtures dont la quantité ou la valeur ne correspond pas au calcul.
     * Même formule que qteClotureTheorique() / valClotureTheorique().
     */
    public function scopeIncoherents(Builder $query): Builder
    {
        return $query->whereRaw(
            '(qte_cloture <> qte_ouverture + qte_in - qte_out + qte_stk
              OR ROUND(val_cloture, 4) <> ROUND(val_ouverture + val_in - val_out + val_stk, 4))'
        );
    }

    /**
     * Quantité de clôture théorique : ouverture + entrées - sorties + écarts d'inventaire.
     * Les sorties (qte_out) sont supposées stockées en valeur positive.
     */
    public function qteClotureTheorique(): int
    {
        return $this->qte_ouverture + $this->qte_in - $this->qte_out + $this->qte_stk;
    }

    public function valClotureTheorique(): string
    {
        return bcadd(
            bcsub(bcadd($this->val_ouverture, $this->val_in, 4), $this->val_out, 4),
            $this->val_stk,
            4
        );
    }

    /**
     * Écart quantité = clôture réelle - clôture théorique (0 = cohérent).
     */
    public function qteEcart(): int
    {
        return $this->qte_cloture - $this->qteClotureTheorique();
    }

    /**
     * Écart valeur = clôture réelle - clôture théorique (0.0000 = cohérent).
     */
    public function valEcart(): string
    {
        return bcsub($this->val_cloture, $this->valClotureTheorique(), 4);
    }

    public function estCoherent(): bool
    {
        return $this->qteEcart() === 0
            && bccomp($this->valEcart(), '0', 4) === 0;
    }
}
