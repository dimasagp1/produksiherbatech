<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'produk_id',
        'odoo_bom_id',
        'version',
        'base_qty',
        'uom_id',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'base_qty' => 'float',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function uom()
    {
        return $this->belongsTo(ScmUom::class, 'uom_id');
    }

    public function items()
    {
        return $this->hasMany(BomItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function detectCategory(string $name, ?string $itemType = null): string
    {
        $n = strtolower($name);
        if (preg_match('/(botol|tutup|plug|dropper|pipet|vial|sachet|tube|jar|pot|can|kapsul|blister|ampul|seal induksi|segel induksi|outer cup|inner bag|stopper)/i', $n) && ! preg_match('/(dus|box|label|lakban|master|karton|shrink|brosur|leaflet)/i', $n)) {
            return 'primary_packaging';
        }
        if (preg_match('/(label|stiker|sticker|dus|dusbox|box|folding box|masterbox|karton|lakban|shrink|mika|plastik shrink|lem|tape|segel|brosur|leaflet|hangtag|corner protector)/i', $n)) {
            return 'secondary_packaging';
        }

        return 'raw_material';
    }

    /**
     * Get multi-level exploded breakdown of composition and packaging for given target quantity (default 1 pcs).
     *
     * @param  array<int>  $visited
     * @return array<string, mixed>
     */
    public function getBreakdown(float $targetQty = 1.0, array $visited = []): array
    {
        if (in_array($this->id, $visited, true)) {
            return [];
        }
        $visited[] = $this->id;

        $items = $this->items()->with(['uom', 'materialProduk.uom'])->get();

        // Check sum of raw materials in this BOM (batch size if it's a ruahan/bulk formula)
        $sumRawWeight = 0;
        foreach ($items as $it) {
            $cat = $it->category ?: self::detectCategory($it->material_name, $it->materialProduk?->item_type);
            if ($cat === 'raw_material' && in_array(strtolower($it->uom?->code ?: $it->uom_name ?: ''), ['g', 'gram', 'gr', 'ml', 'kg', 'l', 'liter'], true)) {
                $sumRawWeight += (float) $it->quantity;
            }
        }

        $baseQty = (float) ($this->base_qty ?: 1.0);
        // If it's a bulk/ruahan formula in g/ml where lines sum to ~1000g/ml, but base_qty is 1 (or 1 batch), and caller asked for specific gram amount (e.g. targetQty = 250)
        if ($sumRawWeight > 1 && $baseQty == 1 && $targetQty > 1) {
            $effectiveBaseQty = $sumRawWeight;
        } else {
            $effectiveBaseQty = $baseQty;
        }

        $scaleFactor = $effectiveBaseQty > 0 ? ($targetQty / $effectiveBaseQty) : $targetQty;

        $rawMaterials = [];
        $primaryPackaging = [];
        $secondaryPackaging = [];
        $tree = [];
        $directItems = [];

        foreach ($items as $item) {
            $reqQty = round($item->quantity * $scaleFactor, 6);
            $matProduk = $item->materialProduk;
            $uomCode = $item->uom?->code ?: ($item->uom_name ?: ($matProduk?->odoo_uom ?: 'Pcs'));
            $matName = $item->material_name ?: ($matProduk?->nama_produk ?: 'Material');
            $cat = $item->category ?: self::detectCategory($matName, $matProduk?->item_type);

            $subBom = null;
            if ($matProduk) {
                $subBom = Bom::where('produk_id', $matProduk->id)->where('is_active', true)->first();
            }

            $node = [
                'material_name' => $matName,
                'material_produk_id' => $matProduk?->id,
                'item_type' => $matProduk?->item_type,
                'quantity' => $reqQty,
                'quantity_per_unit' => round($item->quantity / ($baseQty > 0 ? $baseQty : 1), 6),
                'uom' => $uomCode,
                'category' => $cat,
                'has_sub_bom' => $subBom !== null,
                'children' => [],
            ];

            $directItems[] = $node;

            if ($subBom && ! in_array($subBom->id, $visited, true)) {
                $sub = $subBom->getBreakdown($reqQty, $visited);
                $node['children'] = $sub['tree'] ?? [];
                foreach ($sub['raw_materials'] as $r) {
                    $rawMaterials[] = $r;
                }
                foreach ($sub['primary_packaging'] as $p) {
                    $primaryPackaging[] = $p;
                }
                foreach ($sub['secondary_packaging'] as $s) {
                    $secondaryPackaging[] = $s;
                }
            } else {
                if ($cat === 'raw_material') {
                    $rawMaterials[] = $node;
                } elseif ($cat === 'primary_packaging') {
                    $primaryPackaging[] = $node;
                } else {
                    $secondaryPackaging[] = $node;
                }
            }
            $tree[] = $node;
        }

        // Aggregate duplicate identical materials
        $aggregate = function (array $list) {
            $grouped = [];
            foreach ($list as $item) {
                $key = ($item['material_produk_id'] ?? 0).'_'.strtolower(trim($item['material_name'])).'_'.strtolower(trim($item['uom']));
                if (! isset($grouped[$key])) {
                    $grouped[$key] = $item;
                } else {
                    $grouped[$key]['quantity'] = round($grouped[$key]['quantity'] + $item['quantity'], 6);
                    if (isset($item['quantity_per_unit'])) {
                        $grouped[$key]['quantity_per_unit'] = round($grouped[$key]['quantity_per_unit'] + $item['quantity_per_unit'], 6);
                    }
                }
            }

            return array_values($grouped);
        };

        $rawMaterials = $aggregate($rawMaterials);
        $primaryPackaging = $aggregate($primaryPackaging);
        $secondaryPackaging = $aggregate($secondaryPackaging);

        // Calculate total weight of liquid/bulk raw materials for % composition
        $totalRawWeight = 0;
        foreach ($rawMaterials as $r) {
            if (in_array(strtolower($r['uom']), ['g', 'gram', 'gr', 'ml'], true)) {
                $totalRawWeight += $r['quantity'];
            }
        }

        $formattedRaw = [];
        foreach ($rawMaterials as $r) {
            $pct = $totalRawWeight > 0 && in_array(strtolower($r['uom']), ['g', 'gram', 'gr', 'ml'], true)
                ? round(($r['quantity'] / $totalRawWeight) * 100, 2)
                : null;
            $formattedRaw[] = array_merge($r, ['composition_percentage' => $pct]);
        }

        return [
            'bom_id' => $this->id,
            'version' => $this->version,
            'product_name' => $this->produk?->nama_produk ?? 'Produk',
            'product_code' => $this->produk?->kode_produk ?? '-',
            'product_uom' => $this->uom?->code ?: ($this->produk?->odoo_uom ?: 'Pcs'),
            'target_quantity' => $targetQty,
            'base_quantity' => $baseQty,
            'direct_items' => $directItems,
            'raw_materials' => $formattedRaw,
            'total_raw_material_weight' => round($totalRawWeight, 4),
            'primary_packaging' => $primaryPackaging,
            'secondary_packaging' => $secondaryPackaging,
            'tree' => $tree,
        ];
    }
}
