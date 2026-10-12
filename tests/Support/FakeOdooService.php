<?php

namespace Tests\Support;

use App\Models\Produk;
use App\Models\Setting;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Services\OdooService;

class FakeOdooService extends OdooService
{
    /** @var array<int, array<string, mixed>> */
    public array $moPayload = [];

    /** @var array<int, array<string, mixed>> */
    public array $scrapPayload = [];

    /** @var array<int, array<string, mixed>> */
    public array $saleOrderPayload = [];

    public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 500, int $offset = 0, ?string $order = null): array
    {
        if ($model === 'mrp.production' && $domain !== [] && $this->moPayload !== []) {
            foreach ($domain as $cond) {
                if (is_array($cond) && $cond[0] === 'id' && $cond[1] === 'in') {
                    $ids = array_map('intval', (array) $cond[2]);

                    return array_values(array_filter($this->moPayload, fn ($r) => in_array((int) ($r['id'] ?? 0), $ids, true)));
                }
            }
        }

        return [];
    }

    public function fetchManufacturingOrders(array $domain = [], int $limit = 5000): array
    {
        if ($domain === [] || $this->moPayload === []) {
            return $this->moPayload;
        }
        foreach ($domain as $cond) {
            if (is_array($cond) && $cond[0] === 'id' && $cond[1] === 'in') {
                $ids = array_map('intval', (array) $cond[2]);

                return array_values(array_filter($this->moPayload, fn ($r) => in_array((int) ($r['id'] ?? 0), $ids, true)));
            }
        }

        return $this->moPayload;
    }

    public function fetchManufacturingOrdersPreview(array $domain = [], int $limit = 5000): array
    {
        return parent::fetchManufacturingOrdersPreview($domain, $limit);
    }

    public function syncManufacturingOrders(?array $selectedMoIds = null): array
    {
        $raw = $this->moPayload;
        if (! empty($selectedMoIds)) {
            $ids = array_map('intval', $selectedMoIds);
            $raw = array_values(array_filter($raw, fn ($r) => in_array((int) ($r['id'] ?? 0), $ids, true)));
        }
        $existingPlans = WeeklyPlan::withTrashed()->whereNotNull('odoo_mo_id')->get()->keyBy('odoo_mo_id');
        $localProduks = Produk::whereNotNull('odoo_id')->get()->keyBy('odoo_id');
        $summary = ['created' => 0, 'updated' => 0, 'cancelled' => 0, 'skipped' => 0, 'errors' => []];
        $multiplier = (int) Setting::get('target_output_multiplier', 2000);
        $stateMap = ['confirmed' => 'confirmed', 'progress' => 'in_progress', 'in_progress' => 'in_progress', 'done' => 'done', 'cancel' => 'cancelled', 'cancelled' => 'cancelled'];
        $fallbackCreator = User::where('email', 'ppic@herbatech.com')->value('id') ?? User::orderBy('id')->value('id') ?? 1;
        foreach ($raw as $item) {
            $odooMoId = (int) ($item['id'] ?? 0);
            if ($odooMoId <= 0) {
                continue;
            }
            $state = (string) ($item['state'] ?? 'confirmed');
            $moStatus = $stateMap[$state] ?? 'confirmed';
            $produkId = is_array($item['product_id'] ?? null) ? (int) $item['product_id'][0] : (int) ($item['product_id'] ?? 0);
            $plan = $existingPlans->get($odooMoId);
            if ($moStatus === 'cancelled') {
                if ($plan && ! $plan->trashed()) {
                    $plan->fill(['mo_status' => 'cancelled'])->save();
                    $plan->delete();
                    $summary['cancelled']++;
                } else {
                    $summary['skipped']++;
                }

                continue;
            }
            $batchNumber = $this->parseBatchFromMo($item);
            $tanggal = $this->parseMoDate($item);
            $produk = $produkId > 0 ? $localProduks->get($produkId) : null;
            if (! $produk && $plan === null) {
                $summary['errors'][] = "MO {$item['name']} ({$odooMoId}): produk Odoo #{$produkId} belum ada lokal";
                $summary['skipped']++;

                continue;
            }
            $targetOutput = (int) ($item['product_qty'] ?? 0);
            if ($plan) {
                $plan->fill(['mo_status' => $moStatus, 'target_output' => $targetOutput > 0 ? $targetOutput : $plan->target_output, 'multiplier' => $plan->multiplier ?: $multiplier, 'batch_number' => $batchNumber])->save();
                $summary['updated']++;

                continue;
            }
            $newPlan = WeeklyPlan::create(['produk_id' => $produk->id, 'line_id' => null, 'proses' => $this->parseMoProcess($item, $produk), 'batch_number' => $batchNumber, 'odoo_mo_id' => $odooMoId, 'mo_status' => $moStatus, 'target_output' => $targetOutput, 'mp_count' => 0, 'multiplier' => $multiplier, 'packing_hold' => false, 'tanggal' => $tanggal, 'status' => 'draft', 'created_by' => $fallbackCreator]);
            $existingPlans->put($odooMoId, $newPlan);
            $summary['created']++;
        }

        return $summary;
    }

    public function fetchScrapsFromOdoo(int $limit = 100): array
    {
        return $this->scrapPayload;
    }

    public function fetchMoScraps(int $limit = 200): array
    {
        return $this->scrapPayload;
    }

    public function fetchSaleOrders(array $domain = []): array
    {
        return $this->saleOrderPayload;
    }

    public function postInventoryAdjustment(array $lines): array
    {
        return ['success' => true, 'enabled' => true, 'message' => 'fake write-back ok'];
    }

    public function syncBoms(?array $selectedIds = null): array
    {
        return ['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    public function syncInventoryStocks(?array $selectedIds = null): array
    {
        return ['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    public function syncMaterialUsages(?array $selectedMoIds = null): array
    {
        return ['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    public function fetchWorkCenters(): array
    {
        return [];
    }

    public function fetchBeginningStock(): array
    {
        return [];
    }

    public function syncBeginningStock(): array
    {
        return ['updated' => 0, 'created' => 0, 'errors' => []];
    }
}
