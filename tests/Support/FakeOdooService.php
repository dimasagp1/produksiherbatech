<?php

namespace Tests\Support;

use App\Services\OdooService;

class FakeOdooService extends OdooService
{
    /** @var array<int, array<string, mixed>> */
    public array $moPayload = [];

    /** @var array<int, array<string, mixed>> */
    public array $scrapPayload = [];

    /** @var array<int, array<string, mixed>> */
    public array $saleOrderPayload = [];

    public function fetchManufacturingOrders(array $domain = []): array
    {
        return $this->moPayload;
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

    public function syncBoms(): array
    {
        return ['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    public function syncInventoryStocks(): array
    {
        return ['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }

    public function syncMaterialUsages(int $limit = 50): array
    {
        return ['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []];
    }
}
