<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\InventoryStock;
use App\Models\LaporanHarian;
use App\Models\Line;
use App\Models\MaterialScrap;
use App\Models\MaterialUsage;
use App\Models\Mesin;
use App\Models\Produk;
use App\Models\RejectDetail;
use App\Models\ScmUom;
use App\Models\Setting;
use App\Models\User;
use App\Models\WeeklyPlan;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OdooService
{
    protected string $host;

    protected string $db;

    protected string $username;

    protected string $apiKey;

    protected int $timeout;

    protected ?int $uid = null;

    public function __construct()
    {
        $this->reloadConfig();
    }

    /**
     * Reload settings from Database or fallback to config
     */
    public function reloadConfig(): void
    {
        $this->host = rtrim(Setting::get('odoo_host', config('odoo.host', 'http://localhost:8069')), '/');
        $this->db = Setting::get('odoo_db', config('odoo.db', 'odoo_production'));
        $this->username = Setting::get('odoo_username', config('odoo.username', ''));
        $this->apiKey = Setting::get('odoo_api_key', config('odoo.api_key', ''));
        $this->timeout = (int) Setting::get('odoo_timeout', config('odoo.timeout', 15));
        $this->uid = null;
    }

    /**
     * Check if Odoo configuration is filled
     */
    public function isConfigured(): bool
    {
        return ! empty($this->host) && ! empty($this->db) && ! empty($this->username) && ! empty($this->apiKey);
    }

    /**
     * Get configured HTTP client with valid SSL certificate bundle
     */
    protected function client(): PendingRequest
    {
        $client = Http::timeout($this->timeout);

        if (config('odoo.verify_ssl', env('ODOO_VERIFY_SSL', true)) === false) {
            return $client->withoutVerifying();
        }

        // Search for valid local CA bundle paths to fix broken php.ini curl.cainfo in environments like Laragon
        $caPaths = [
            'C:\\laragon\\etc\\ssl\\cacert.pem',
            'C:/laragon/etc/ssl/cacert.pem',
            'C:\\Users\\Herbatech\\.config\\herd\\config\\php\\cacert.pem',
        ];

        foreach ($caPaths as $path) {
            if (file_exists($path)) {
                return $client->withOptions(['verify' => $path]);
            }
        }

        return $client;
    }

    /**
     * Authenticate with Odoo JSON-RPC
     */
    public function authenticate(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        if (! $this->isConfigured()) {
            throw new Exception('Konfigurasi Odoo (.env) belum lengkap. Pastikan ODOO_HOST, ODOO_DB, ODOO_USERNAME, dan ODOO_API_KEY sudah terisi.');
        }

        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => 'common',
                'method' => 'authenticate',
                'args' => [
                    $this->db,
                    $this->username,
                    $this->apiKey,
                    new \stdClass,
                ],
            ],
            'id' => rand(1000, 9999),
        ];

        try {
            $response = $this->client()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->host.'/jsonrpc', $payload);

            if (! $response->successful()) {
                throw new Exception('Gagal menghubungi server Odoo: HTTP '.$response->status());
            }

            $body = $response->json();
            if (isset($body['error'])) {
                $errMsg = $body['error']['data']['message'] ?? $body['error']['message'] ?? 'Odoo RPC Error';
                throw new Exception('Odoo Auth Error: '.$errMsg);
            }

            $uid = $body['result'] ?? false;
            if (! $uid || ! is_numeric($uid)) {
                throw new Exception('Autentikasi Odoo gagal. Periksa Username, Database, dan API Key / Password Anda.');
            }

            $this->uid = (int) $uid;

            return $this->uid;
        } catch (Exception $e) {
            Log::error('Odoo Authentication Failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Override config in-memory (e.g. for testing connection with unsaved input)
     */
    public function setConfig(array $config): void
    {
        if (isset($config['odoo_host'])) {
            $this->host = rtrim($config['odoo_host'], '/');
        }
        if (isset($config['odoo_db'])) {
            $this->db = trim($config['odoo_db']);
        }
        if (isset($config['odoo_username'])) {
            $this->username = trim($config['odoo_username']);
        }
        if (isset($config['odoo_api_key']) && $config['odoo_api_key'] !== '') {
            $this->apiKey = trim($config['odoo_api_key']);
        }
        if (isset($config['odoo_timeout'])) {
            $this->timeout = (int) $config['odoo_timeout'];
        }
        $this->uid = null;
    }

    /**
     * Test connection to Odoo server
     */
    public function testConnection(?array $customConfig = null): array
    {
        if ($customConfig) {
            $this->setConfig($customConfig);
        }

        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Konfigurasi Odoo belum lengkap. Silakan lengkapi ODOO_HOST, ODOO_DB, ODOO_USERNAME, dan ODOO_API_KEY.',
            ];
        }

        try {
            $uid = $this->authenticate();

            // Get Odoo Version
            $versionPayload = [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'version',
                    'args' => [],
                ],
                'id' => rand(1000, 9999),
            ];

            $res = $this->client()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->host.'/jsonrpc', $versionPayload);
            $versionData = $res->json('result') ?? [];
            $serverVersion = $versionData['server_version'] ?? 'Terdeteksi';

            return [
                'success' => true,
                'message' => "Koneksi ke Odoo berhasil (User ID: {$uid}, Versi Server: {$serverVersion}).",
                'uid' => $uid,
                'server_version' => $serverVersion,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generic execute_kw call on Odoo Models
     */
    public function executeKw(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $uid = $this->authenticate();

        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => 'object',
                'method' => 'execute_kw',
                'args' => [
                    $this->db,
                    $uid,
                    $this->apiKey,
                    $model,
                    $method,
                    $args,
                    (object) $kwargs,
                ],
            ],
            'id' => rand(1000, 9999),
        ];

        $response = $this->client()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->host.'/jsonrpc', $payload);

        if (! $response->successful()) {
            throw new Exception("HTTP error saat memanggil model {$model}: ".$response->status());
        }

        $body = $response->json();
        if (isset($body['error'])) {
            $msg = $body['error']['data']['message'] ?? $body['error']['message'] ?? 'RPC Error';
            throw new Exception("Odoo Model Error ({$model}::{$method}): ".$msg);
        }

        return $body['result'] ?? null;
    }

    /**
     * Search and read records from Odoo
     */
    public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 500, int $offset = 0, ?string $order = null): array
    {
        $kwargs = [
            'fields' => $fields,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($order !== null) {
            $kwargs['order'] = $order;
        }

        $result = $this->executeKw($model, 'search_read', [$domain], $kwargs);

        return is_array($result) ? $result : [];
    }

    /**
     * Fetch products from Odoo for preview and selection
     */
    public function fetchProductsPreview(): array
    {
        $domain = config('odoo.products.domain', [['active', '=', true]]);
        $fields = ['id', 'name', 'default_code', 'product_tmpl_id', 'uom_id', 'active'];

        $odooProducts = $this->searchRead('product.product', $domain, $fields);

        // Get local products for matching
        $localProducts = Produk::all(['id', 'odoo_id', 'kode_produk', 'nama_produk']);
        $localByOdooId = $localProducts->whereNotNull('odoo_id')->keyBy('odoo_id');
        $localByKode = $localProducts->whereNotNull('kode_produk')->keyBy('kode_produk');
        $localByName = $localProducts->keyBy(fn ($p) => strtolower(trim($p->nama_produk)));

        $result = [];
        $seenIds = [];

        foreach ($odooProducts as $item) {
            $odooId = (int) $item['id'];
            if (isset($seenIds[$odooId])) {
                continue;
            }
            $seenIds[$odooId] = true;

            $name = trim($item['name'] ?? '');
            $code = trim($item['default_code'] ?? '') ?: 'OD-'.$odooId;
            $uom = is_array($item['uom_id'] ?? null) ? $item['uom_id'][1] : null;
            $active = (bool) ($item['active'] ?? true);

            if (empty($name)) {
                continue;
            }

            $matched = $localByOdooId->get($odooId)
                ?? $localByKode->get($code)
                ?? $localByName->get(strtolower($name));

            $result[] = [
                'odoo_id' => $odooId,
                'name' => $name,
                'default_code' => $code,
                'uom' => $uom,
                'active' => $active,
                'exists_in_local' => $matched !== null,
                'local_id' => $matched?->id,
                'local_name' => $matched?->nama_produk,
            ];
        }

        return $result;
    }

    /**
     * Sync Products from Odoo (product.product) to local DB
    /**
     * Helper untuk memetakan nama kategori Odoo ke item_type lokal (fg, rm, pm, wip).
     */
    public static function determineItemType(?string $categoryName): string
    {
        if (empty($categoryName)) {
            return 'fg';
        }
        $catLower = strtolower($categoryName);
        if (str_contains($catLower, 'raw') || str_contains($catLower, 'baku')) {
            return 'rm';
        }
        if (str_contains($catLower, 'packag') || str_contains($catLower, 'kemas')) {
            return 'pm';
        }
        if (str_contains($catLower, 'wip')) {
            return 'wip';
        }

        return 'fg';
    }

    /**
     * Sync products from Odoo to local DB.
     *
     * If $selectedIds is provided, only sync those specific Odoo IDs
     */
    public function syncProducts(?array $selectedIds = null): array
    {
        $domain = config('odoo.products.domain', [['active', '=', true]]);
        if (! empty($selectedIds)) {
            $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));
            $domain[] = ['id', 'in', $selectedIds];
        }

        $fields = ['id', 'name', 'default_code', 'product_tmpl_id', 'uom_id', 'categ_id', 'active'];

        $odooProducts = $this->searchRead('product.product', $domain, $fields, 5000);

        $created = 0;
        $updated = 0;
        $processedOdooIds = [];

        $uomByCode = ScmUom::pluck('id', 'code');
        $uomByName = ScmUom::pluck('id', 'name');

        DB::transaction(function () use ($odooProducts, $uomByCode, $uomByName, &$created, &$updated, &$processedOdooIds) {
            foreach ($odooProducts as $item) {
                $odooId = (int) $item['id'];
                if (isset($processedOdooIds[$odooId])) {
                    continue;
                }
                $processedOdooIds[$odooId] = true;

                $name = trim($item['name'] ?? '');
                $code = trim($item['default_code'] ?? '') ?: 'OD-'.$odooId;
                $uom = is_array($item['uom_id'] ?? null) ? $item['uom_id'][1] : null;
                $active = (bool) ($item['active'] ?? true);
                $categName = is_array($item['categ_id'] ?? null) ? $item['categ_id'][1] : (is_string($item['categ_id'] ?? null) ? $item['categ_id'] : null);
                $itemType = self::determineItemType($categName);

                if (empty($name)) {
                    continue;
                }

                $uomId = null;
                if ($uom) {
                    $uomId = $uomByCode->get(strtoupper($uom))
                        ?? $uomByName->get($uom)
                        ?? $uomByCode->get(strtolower($uom));
                }

                // Find by odoo_id, kode_produk, or case-insensitive exact name (including trashed)
                $produk = Produk::withTrashed()
                    ->where(function ($q) use ($odooId, $code) {
                        $q->where('odoo_id', $odooId)
                            ->orWhere('kode_produk', $code);
                    })
                    ->first();

                if (! $produk) {
                    $produk = Produk::withTrashed()->where('nama_produk', $name)->first();
                }

                if ($produk) {
                    if ($produk->trashed()) {
                        $produk->restore();
                    }
                    $produk->update([
                        'odoo_id' => $odooId,
                        'kode_produk' => $produk->kode_produk ?: $code,
                        'nama_produk' => $name,
                        'item_type' => $itemType,
                        'odoo_uom' => $uom,
                        'uom_id' => $uomId ?? $produk->uom_id,
                        'status_aktif' => $active,
                        'odoo_synced_at' => now(),
                    ]);
                    $updated++;
                } else {
                    // Ensure unique kode_produk
                    $finalCode = $code;
                    $counter = 1;
                    while (Produk::withTrashed()->where('kode_produk', $finalCode)->exists()) {
                        $finalCode = $code.'-'.$counter;
                        $counter++;
                    }

                    Produk::create([
                        'odoo_id' => $odooId,
                        'kode_produk' => $finalCode,
                        'nama_produk' => $name,
                        'item_type' => $itemType,
                        'proses_default' => config('odoo.products.default_process', 'mixing'),
                        'odoo_uom' => $uom,
                        'uom_id' => $uomId,
                        'status_aktif' => $active,
                        'odoo_synced_at' => now(),
                    ]);
                    $created++;
                }
            }
        });

        return [
            'total_from_odoo' => count($processedOdooIds),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Fetch Scrap / Rejects from Odoo stock.scrap
     */
    public function fetchScrapsFromOdoo(int $limit = 100): array
    {
        $domain = [];
        $fields = ['id', 'name', 'product_id', 'scrap_qty', 'date_done', 'origin', 'state', 'create_date', 'lot_id', 'location_id', 'scrap_location_id', 'product_uom_id', 'create_uid'];

        $raw = $this->searchRead('stock.scrap', $domain, $fields, $limit);

        return array_map(function ($item) {
            $productName = is_array($item['product_id'] ?? null) ? $item['product_id'][1] : 'Produk #'.($item['product_id'] ?? '-');
            $productId = is_array($item['product_id'] ?? null) ? $item['product_id'][0] : $item['product_id'];
            $lotName = is_array($item['lot_id'] ?? null) ? $item['lot_id'][1] : null;
            $locationName = is_array($item['location_id'] ?? null) ? $item['location_id'][1] : ($item['location_id'] ?: 'Stock');
            $scrapLocationName = is_array($item['scrap_location_id'] ?? null) ? $item['scrap_location_id'][1] : ($item['scrap_location_id'] ?: 'Scrap');
            $uomName = is_array($item['product_uom_id'] ?? null) ? $item['product_uom_id'][1] : 'Pcs';
            $creatorName = is_array($item['create_uid'] ?? null) ? $item['create_uid'][1] : 'System';

            $origin = $item['origin'] ?: '-';
            $batchNumber = $lotName;
            if (! $batchNumber && $origin !== '-') {
                if (preg_match('/(?:Batch:\s*|LinePulse\s*-\s*)([A-Za-z0-9\-\/]+)/i', $origin, $matches)) {
                    $batchNumber = trim($matches[1]);
                } else {
                    $batchNumber = $origin;
                }
            }

            return [
                'id' => $item['id'],
                'name' => $item['name'] ?? ('SP/'.$item['id']),
                'product_id' => $productId,
                'product_name' => $productName,
                'scrap_qty' => (float) ($item['scrap_qty'] ?? 0),
                'uom' => $uomName,
                'date' => $item['date_done'] ?: ($item['create_date'] ?? null),
                'origin' => $origin,
                'batch_number' => $batchNumber ?: '-',
                'location' => $locationName,
                'scrap_location' => $scrapLocationName,
                'creator' => $creatorName,
                'state' => $item['state'] ?? 'done',
            ];
        }, $raw);
    }

    /**
     * Push a local RejectDetail record to Odoo stock.scrap
     */
    public function pushRejectToOdoo(RejectDetail $reject): array
    {
        $laporan = $reject->laporanHarian;
        $produk = $laporan?->produk;

        if (! $produk || ! $produk->odoo_id) {
            throw new Exception('Produk terkait belum memiliki Odoo ID. Sinkronkan produk terlebih dahulu.');
        }

        $batchNumber = $laporan->batch_number ?? '-';
        $proses = $laporan->proses ?? '-';

        $scrapValues = [
            'product_id' => $produk->odoo_id,
            'scrap_qty' => (float) $reject->jumlah,
            'origin' => "Batch: {$batchNumber} | {$proses}",
            'note' => "Reject {$reject->jenis_reject} [Batch: {$batchNumber}]: ".($reject->keterangan ?? '-'),
        ];

        $scrapId = $this->executeKw('stock.scrap', 'create', [$scrapValues]);

        if ($scrapId && is_numeric($scrapId)) {
            $reject->update([
                'odoo_scrap_id' => (int) $scrapId,
                'odoo_synced_at' => now(),
            ]);

            return [
                'success' => true,
                'odoo_scrap_id' => $scrapId,
            ];
        }

        return [
            'success' => false,
            'message' => 'Gagal membuat scrap order di Odoo',
        ];
    }

    /**
     * Fetch Manufacturing Orders (mrp.production) from Odoo (strictly confirmed status for planning).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchManufacturingOrders(array $domain = [], int $limit = 5000): array
    {
        $domain = $domain !== [] ? $domain : [
            ['state', '=', 'confirmed'],
        ];

        return $this->searchRead('mrp.production', $domain, [
            'id', 'name', 'origin', 'state',
            'product_id', 'product_qty', 'bom_id',
            'lot_producing_id',
            'date_start', 'date_finished',
        ], $limit, 0, 'id desc');
    }

    /**
     * Fetch Manufacturing Orders (mrp.production) preview with local status for selection modal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchManufacturingOrdersPreview(array $domain = [], int $limit = 5000): array
    {
        $raw = $this->fetchManufacturingOrders($domain, $limit);
        $existingPlans = WeeklyPlan::withTrashed()->whereNotNull('odoo_mo_id')->get()->keyBy('odoo_mo_id');
        $localProduks = Produk::whereNotNull('odoo_id')->get()->keyBy('odoo_id');

        $stateMap = [
            'confirmed' => 'confirmed',
            'progress' => 'in_progress',
            'in_progress' => 'in_progress',
            'done' => 'done',
            'cancel' => 'cancelled',
            'cancelled' => 'cancelled',
        ];

        $result = [];
        foreach ($raw as $item) {
            $odooMoId = (int) ($item['id'] ?? 0);
            if ($odooMoId <= 0) {
                continue;
            }

            $state = (string) ($item['state'] ?? 'confirmed');
            $moStatus = $stateMap[$state] ?? 'confirmed';
            $produkId = is_array($item['product_id'] ?? null) ? (int) $item['product_id'][0] : (int) ($item['product_id'] ?? 0);
            $productName = is_array($item['product_id'] ?? null) ? (string) $item['product_id'][1] : ('Produk #'.$produkId);

            $produk = $produkId > 0 ? $localProduks->get($produkId) : null;
            $plan = $existingPlans->get($odooMoId);

            $batchNumber = $this->parseBatchFromMo($item);
            $tanggal = $this->parseMoDate($item);
            $targetOutput = (int) ($item['product_qty'] ?? 0);
            $proses = $plan?->proses ?: $this->parseMoProcess($item, $produk);

            $canSync = ($produk !== null) || ($plan !== null);
            $syncStatus = 'ready';
            if ($moStatus === 'cancelled') {
                $syncStatus = 'cancelled';
            } elseif (! $canSync) {
                $syncStatus = 'missing_product';
            } elseif ($plan !== null) {
                $syncStatus = 'exists';
            }

            $result[] = [
                'odoo_mo_id' => $odooMoId,
                'name' => (string) ($item['name'] ?? ('MO/'.$odooMoId)),
                'origin' => (string) ($item['origin'] ?? '-'),
                'odoo_product_id' => $produkId,
                'product_name' => $productName,
                'exists_in_local' => $produk !== null,
                'local_product_id' => $produk?->id,
                'local_product_name' => $produk?->nama_produk,
                'batch_number' => $batchNumber,
                'proses' => $proses,
                'target_output' => $targetOutput,
                'tanggal' => $tanggal,
                'state' => $state,
                'mo_status' => $moStatus,
                'plan_exists' => $plan !== null,
                'plan_status' => $plan?->status,
                'can_sync' => $canSync,
                'sync_status' => $syncStatus,
            ];
        }

        return $result;
    }

    public function parseMoProcess(array $item, ?Produk $produk = null): string
    {
        $searchStr = strtolower(($item['origin'] ?? '').' '.($item['name'] ?? ''));
        if (str_contains($searchStr, 'packing') || str_contains($searchStr, 'kemas') || str_contains($searchStr, 'pack') || str_contains($searchStr, 'pck')) {
            return 'packing';
        }
        if (str_contains($searchStr, 'filling') || str_contains($searchStr, 'isi') || str_contains($searchStr, 'fill') || str_contains($searchStr, 'fil')) {
            return 'filling';
        }
        if (str_contains($searchStr, 'mixing') || str_contains($searchStr, 'olah') || str_contains($searchStr, 'mix')) {
            return 'mixing';
        }

        return $produk?->proses_default ?: 'mixing';
    }

    /**
     * Sync Odoo Manufacturing Orders into local weekly_plans (manual button, on-demand).
     *
     * @param  array<int>|null  $selectedMoIds
     * @return array{created:int,updated:int,cancelled:int,skipped:int,errors:array<int,string>}
     */
    public function syncManufacturingOrders(?array $selectedMoIds = null): array
    {
        $domain = [];
        if (! empty($selectedMoIds)) {
            $selectedMoIds = array_values(array_unique(array_map('intval', $selectedMoIds)));
            $domain[] = ['id', 'in', $selectedMoIds];
        } else {
            $domain = [['state', '=', 'confirmed']];
        }

        $raw = $this->fetchManufacturingOrders($domain);

        $existingPlans = WeeklyPlan::withTrashed()->whereNotNull('odoo_mo_id')->get()->keyBy('odoo_mo_id');
        $existingMoIds = $existingPlans->keys()->all();

        // Check if existing synced plans have been cancelled in Odoo
        if (! empty($existingMoIds) && empty($selectedMoIds)) {
            $checkedExisting = $this->searchRead('mrp.production', [['id', 'in', $existingMoIds]], [
                'id', 'name', 'origin', 'state',
                'product_id', 'product_qty', 'bom_id',
                'lot_producing_id',
                'date_start', 'date_finished',
            ], 1000);
            $raw = array_values(collect($raw)->concat($checkedExisting)->unique('id')->all());
        }

        $summary = ['created' => 0, 'updated' => 0, 'cancelled' => 0, 'skipped' => 0, 'errors' => []];
        $multiplier = (int) Setting::get('target_output_multiplier', 2000);

        $stateMap = [
            'confirmed' => 'confirmed',
            'progress' => 'in_progress',
            'in_progress' => 'in_progress',
            'done' => 'done',
            'cancel' => 'cancelled',
            'cancelled' => 'cancelled',
        ];

        $existingPlans = WeeklyPlan::withTrashed()->whereNotNull('odoo_mo_id')->get()->keyBy('odoo_mo_id');
        $localProduks = Produk::whereNotNull('odoo_id')->get()->keyBy('odoo_id');
        $fallbackCreator = $this->fallbackCreatorId();

        foreach ($raw as $item) {
            $odooMoId = (int) ($item['id'] ?? 0);
            if ($odooMoId <= 0) {
                continue;
            }

            $state = (string) ($item['state'] ?? 'confirmed');
            $moStatus = $stateMap[$state] ?? 'confirmed';
            $produkId = is_array($item['product_id'] ?? null) ? (int) $item['product_id'][0] : (int) ($item['product_id'] ?? 0);

            try {
                $plan = $existingPlans->get($odooMoId);

                if ($moStatus === 'cancelled') {
                    if ($plan && ! $plan->trashed()) {
                        $plan->applyOdooSync(['mo_status' => 'cancelled']);
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
                    $plan->applyOdooSync([
                        'mo_status' => $moStatus,
                        'target_output' => $targetOutput > 0 ? $targetOutput : $plan->target_output,
                        'multiplier' => $plan->multiplier ?: $multiplier,
                        'batch_number' => $batchNumber,
                    ]);
                    $summary['updated']++;

                    continue;
                }

                $newPlan = WeeklyPlan::create([
                    'produk_id' => $produk->id,
                    'line_id' => null,
                    'proses' => $this->parseMoProcess($item, $produk),
                    'batch_number' => $batchNumber,
                    'odoo_mo_id' => $odooMoId,
                    'mo_status' => $moStatus,
                    'target_output' => $targetOutput,
                    'mp_count' => 0,
                    'multiplier' => $multiplier,
                    'packing_hold' => false,
                    'tanggal' => $tanggal,
                    'status' => 'draft',
                    'created_by' => $fallbackCreator,
                ]);
                $existingPlans->put($odooMoId, $newPlan);
                $summary['created']++;
            } catch (Exception $e) {
                $summary['errors'][] = "MO #{$odooMoId}: ".$e->getMessage();
            }
        }

        return $summary;
    }

    public function parseBatchFromMo(array $item): string
    {
        // 1. Lot / Serial Number dari Odoo mrp.production (lot_producing_id)
        if (! empty($item['lot_producing_id'])) {
            if (is_array($item['lot_producing_id']) && isset($item['lot_producing_id'][1]) && trim((string) $item['lot_producing_id'][1]) !== '') {
                return trim((string) $item['lot_producing_id'][1]);
            }
            if (is_string($item['lot_producing_id']) && trim($item['lot_producing_id']) !== '') {
                return trim($item['lot_producing_id']);
            }
        }

        if (! empty($item['lot_id'])) {
            if (is_array($item['lot_id']) && isset($item['lot_id'][1]) && trim((string) $item['lot_id'][1]) !== '') {
                return trim((string) $item['lot_id'][1]);
            }
            if (is_string($item['lot_id']) && trim($item['lot_id']) !== '') {
                return trim($item['lot_id']);
            }
        }

        if (! empty($item['lot_name']) && is_string($item['lot_name']) && trim($item['lot_name']) !== '') {
            return trim($item['lot_name']);
        }

        // 2. Fallback origin (e.g. "Batch: BATCH-001" atau "LinePulse - BATCH-001")
        $origin = trim((string) ($item['origin'] ?? ''));
        if ($origin !== '' && preg_match('/(?:Batch:\s*|LinePulse\s*-\s*)([A-Za-z0-9\-\/]+)/i', $origin, $matches)) {
            return trim($matches[1]);
        }
        if ($origin !== '') {
            return $origin;
        }

        // 3. Fallback MO Reference / name
        return trim((string) ($item['name'] ?? ('MO/'.($item['id'] ?? '0'))));
    }

    protected function parseMoDate(array $item): string
    {
        $start = (string) ($item['date_start'] ?? '');
        if ($start !== '' && preg_match('/(\d{4}-\d{2}-\d{2})/', $start, $matches)) {
            return $matches[1];
        }

        return now()->toDateString();
    }

    /**
     * created_by untuk path sync CLI/tinker (tanpa auth HTTP).
     */
    protected function fallbackCreatorId(): int
    {
        return (int) (auth()->id()
            ?? User::where('email', 'ppic@herbatech.com')->value('id')
            ?? User::orderBy('id')->value('id'));
    }

    /**
     * Pull scrap material dari Odoo (stock.scrap state=done) — arah Odoo → lokal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchMoScraps(int $limit = 200): array
    {
        $domain = [['state', '=', 'done']];
        $raw = $this->searchRead('stock.scrap', $domain, [
            'id', 'name', 'product_id', 'scrap_qty', 'date_done', 'origin', 'state',
            'product_uom_id', 'create_date',
        ], $limit);

        return array_map(function ($item) {
            $productName = is_array($item['product_id'] ?? null) ? $item['product_id'][1] : ('Material #'.($item['product_id'] ?? '-'));
            $uomName = is_array($item['product_uom_id'] ?? null) ? $item['product_uom_id'][1] : null;
            $origin = (string) ($item['origin'] ?? '');
            $batch = $this->parseBatchFromMo(['origin' => $origin, 'name' => $item['name'] ?? null, 'id' => $item['id'] ?? 0]);

            return [
                'odoo_scrap_id' => (int) ($item['id'] ?? 0),
                'name' => $item['name'] ?? ('SP/'.($item['id'] ?? '-')),
                'material_name' => $productName,
                'quantity' => (float) ($item['scrap_qty'] ?? 0),
                'uom' => $uomName,
                'batch_number' => $batch,
                'origin' => $origin,
                'date' => $item['date_done'] ?: ($item['create_date'] ?? null),
            ];
        }, $raw);
    }

    /**
     * Sync scrap material Odoo → lokal (source=odoo). Idempoten via odoo_scrap_id.
     *
     * @return array{inserted:int,skipped:int,errors:array<int,string>}
     */
    public function syncMoScraps(): array
    {
        $rows = $this->fetchMoScraps();
        $summary = ['inserted' => 0, 'skipped' => 0, 'errors' => []];
        $uomByCode = ScmUom::pluck('id', 'code');

        foreach ($rows as $row) {
            $odooId = (int) $row['odoo_scrap_id'];
            if ($odooId <= 0 || $row['quantity'] <= 0) {
                $summary['skipped']++;

                continue;
            }

            try {
                if (MaterialScrap::where('odoo_scrap_id', $odooId)->exists()) {
                    $summary['skipped']++;

                    continue;
                }

                MaterialScrap::create([
                    'batch_number' => $row['batch_number'] ?: '-',
                    'weekly_plan_id' => WeeklyPlan::where('batch_number', $row['batch_number'])->value('id'),
                    'produk_id' => null,
                    'material_name' => $row['material_name'],
                    'quantity' => $row['quantity'],
                    'uom_id' => $uomByCode->get(strtoupper((string) $row['uom'])),
                    'source' => 'odoo',
                    'defect_reason' => 'Scrap dari Odoo ('.$row['name'].')',
                    'odoo_scrap_id' => $odooId,
                    'created_by' => $this->fallbackCreatorId(),
                ]);
                $summary['inserted']++;
            } catch (Exception $e) {
                $summary['errors'][] = "Scrap #{$odooId}: ".$e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * Pull Sale Order unfulfilled dari Odoo untuk Plan Delivery Schedule.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchSaleOrders(array $domain = []): array
    {
        $domain = $domain !== [] ? $domain : [
            ['state', 'in', ['sale', 'done']],
            ['delivery_status', '!=', 'done'],
        ];

        try {
            return $this->searchRead('sale.order', $domain, [
                'id', 'name', 'partner_id', 'date_order', 'amount_untaxed', 'amount_total', 'state',
            ], 200);
        } catch (Exception $e) {
            // delivery_status field optional di beberapa Odoo — fallback tanpa domain itu
            if (str_contains($e->getMessage(), 'delivery_status')) {
                return $this->searchRead('sale.order', [['state', 'in', ['sale', 'done']]], [
                    'id', 'name', 'partner_id', 'date_order', 'amount_untaxed', 'amount_total', 'state',
                ], 200);
            }
            throw $e;
        }
    }

    /**
     * SPIKE: Odoo write-back stock adjustment (Phase C C1.5).
     * Flag: Setting odoo_inventory_writeback_enabled (default 0).
     *
     * @param  array<int, array{produk_id:int, quantity:float, location?:string}>  $lines
     * @return array{success:bool,message:string,enabled:bool}
     */
    public function postInventoryAdjustment(array $lines): array
    {
        $enabled = filter_var(
            Setting::get('odoo_inventory_writeback_enabled', '0'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $enabled) {
            return [
                'success' => false,
                'enabled' => false,
                'message' => 'Write-back Odoo nonaktif. Aktifkan setting odoo_inventory_writeback_enabled setelah spike model stock.quant/adjustment selesai.',
            ];
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'enabled' => true, 'message' => 'Konfigurasi Odoo belum lengkap.'];
        }

        try {
            $payload = [];
            foreach ($lines as $line) {
                $produk = Produk::find($line['produk_id'] ?? 0);
                if (! $produk?->odoo_id) {
                    continue;
                }
                $payload[] = [
                    'product_id' => $produk->odoo_id,
                    'inventory_quantity' => (float) ($line['quantity'] ?? 0),
                    'location_id' => $line['location'] ?? false,
                ];
            }

            if ($payload === []) {
                return ['success' => false, 'enabled' => true, 'message' => 'Tidak ada baris dengan Odoo ID untuk write-back.'];
            }

            // Spike path: apply.inventory.group / stock.quant inventory quantity set
            $this->executeKw('stock.quant', 'action_apply_inventory', [[]], []);

            return [
                'success' => true,
                'enabled' => true,
                'message' => 'Write-back Odoo dipanggil untuk '.count($payload).' baris (spike — validasi di Odoo staging).',
            ];
        } catch (Exception $e) {
            return ['success' => false, 'enabled' => true, 'message' => 'Gagal write-back Odoo: '.$e->getMessage()];
        }
    }

    /**
     * Fetch Inventory Stocks preview from Odoo with local vs Odoo stock.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchInventoryStocksPreview(): array
    {
        $products = $this->searchRead('product.product', [['active', '=', true]], [
            'id', 'name', 'default_code', 'qty_available', 'uom_id', 'categ_id',
        ], 1000);

        $localProduks = Produk::with('inventoryStocks')->get();
        $localByOdooId = $localProduks->whereNotNull('odoo_id')->keyBy('odoo_id');
        $localByCode = $localProduks->whereNotNull('kode_produk')->keyBy('kode_produk');
        $localByName = $localProduks->keyBy(fn ($p) => strtolower(trim($p->nama_produk)));

        $result = [];
        foreach ($products as $p) {
            $odooId = (int) $p['id'];
            $name = trim($p['name'] ?? '');
            $code = trim($p['default_code'] ?? '') ?: 'OD-'.$odooId;
            $odooQty = (float) ($p['qty_available'] ?? 0);
            $uom = is_array($p['uom_id'] ?? null) ? $p['uom_id'][1] : null;
            $categName = is_array($p['categ_id'] ?? null) ? $p['categ_id'][1] : (is_string($p['categ_id'] ?? null) ? $p['categ_id'] : null);
            $itemType = self::determineItemType($categName);

            $localProduk = $localByOdooId->get($odooId)
                ?? $localByCode->get($code)
                ?? $localByName->get(strtolower($name));

            $localQty = $localProduk ? (float) $localProduk->inventoryStocks()->sum('quantity') : 0;

            $result[] = [
                'odoo_id' => $odooId,
                'name' => $name,
                'code' => $code,
                'item_type' => $itemType,
                'uom' => $uom,
                'odoo_qty' => $odooQty,
                'local_qty' => $localQty,
                'difference' => $odooQty - $localQty,
                'exists_in_local' => $localProduk !== null,
                'local_id' => $localProduk?->id,
            ];
        }

        return $result;
    }

    /**
     * Pull dan sync Saldo Stok dari Odoo (product.product qty_available) untuk FG, RM, PM, dan WIP.
     *
     * @param  array<int>|null  $selectedIds
     * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function syncInventoryStocks(?array $selectedIds = null): array
    {
        $summary = ['updated' => 0, 'created' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $domain = [['active', '=', true]];
            if (! empty($selectedIds)) {
                $selectedIds = array_values(array_unique(array_map('intval', $selectedIds)));
                $domain[] = ['id', 'in', $selectedIds];
            }

            $products = $this->searchRead('product.product', $domain, [
                'id', 'name', 'default_code', 'qty_available', 'uom_id', 'categ_id',
            ], 5000);

            $uomByCode = ScmUom::pluck('id', 'code');
            $uomByName = ScmUom::pluck('id', 'name');

            foreach ($products as $p) {
                $odooId = (int) $p['id'];
                $qty = (float) ($p['qty_available'] ?? 0);
                $name = trim($p['name'] ?? '');
                $code = trim($p['default_code'] ?? '') ?: 'OD-'.$odooId;
                $categName = is_array($p['categ_id'] ?? null) ? $p['categ_id'][1] : (is_string($p['categ_id'] ?? null) ? $p['categ_id'] : null);
                $itemType = self::determineItemType($categName);
                $uomName = is_array($p['uom_id'] ?? null) ? $p['uom_id'][1] : null;

                if (empty($name)) {
                    continue;
                }

                $uomId = null;
                if ($uomName) {
                    $uomId = $uomByCode->get(strtoupper($uomName))
                        ?? $uomByName->get($uomName)
                        ?? $uomByCode->get(strtolower($uomName));
                }

                $localProduk = Produk::withTrashed()
                    ->where(function ($q) use ($odooId, $code) {
                        $q->where('odoo_id', $odooId)
                            ->orWhere('kode_produk', $code);
                    })
                    ->first();

                if (! $localProduk) {
                    $localProduk = Produk::withTrashed()->where('nama_produk', $name)->first();
                }

                if ($localProduk) {
                    if ($localProduk->trashed()) {
                        $localProduk->restore();
                    }
                    $localProduk->update([
                        'odoo_id' => $odooId,
                        'nama_produk' => $name,
                        'item_type' => $itemType,
                        'odoo_uom' => $uomName,
                        'uom_id' => $uomId ?? $localProduk->uom_id,
                        'status_aktif' => true,
                        'odoo_synced_at' => now(),
                    ]);
                } else {
                    $finalCode = $code;
                    $counter = 1;
                    while (Produk::withTrashed()->where('kode_produk', $finalCode)->exists()) {
                        $finalCode = $code.'-'.$counter;
                        $counter++;
                    }

                    $localProduk = Produk::create([
                        'odoo_id' => $odooId,
                        'kode_produk' => $finalCode,
                        'nama_produk' => $name,
                        'item_type' => $itemType,
                        'proses_default' => 'mixing',
                        'odoo_uom' => $uomName,
                        'uom_id' => $uomId,
                        'status_aktif' => true,
                        'odoo_synced_at' => now(),
                    ]);
                }

                $stock = InventoryStock::updateOrCreate(
                    [
                        'produk_id' => $localProduk->id,
                        'location' => 'GUDANG-UTAMA',
                        'batch_number' => null,
                    ],
                    [
                        'quantity' => $qty,
                        'expired_date' => null,
                    ]
                );

                if ($stock->wasRecentlyCreated) {
                    $summary['created']++;
                } else {
                    $summary['updated']++;
                }
            }
        } catch (Exception $e) {
            $summary['errors'][] = 'Inventory Stock Sync: '.$e->getMessage();
        }

        return $summary;
    }

    /**
     * Fetch Material Usages preview from Odoo stock.move linked to active/confirmed MOs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchMaterialUsagesPreview(): array
    {
        $moves = $this->searchRead('stock.move', [
            ['raw_material_production_id', '!=', false],
            ['state', 'in', ['done', 'progress']],
        ], [
            'id', 'raw_material_production_id', 'product_id', 'quantity', 'product_uom_qty', 'product_uom', 'date',
        ], 1000);

        if (empty($moves)) {
            return [];
        }

        $movesByMo = [];
        foreach ($moves as $m) {
            $moId = is_array($m['raw_material_production_id']) ? (int) $m['raw_material_production_id'][0] : (int) $m['raw_material_production_id'];
            $movesByMo[$moId][] = $m;
        }

        $moIds = array_keys($movesByMo);
        $odooMos = [];
        if (! empty($moIds)) {
            $moData = $this->searchRead('mrp.production', [['id', 'in', $moIds]], [
                'id', 'name', 'product_id', 'product_qty', 'lot_producing_id', 'state', 'date_start',
            ], count($moIds));
            foreach ($moData as $mo) {
                $odooMos[(int) $mo['id']] = $mo;
            }
        }

        $weeklyPlans = WeeklyPlan::with(['produk.uom', 'line'])->whereIn('odoo_mo_id', $moIds)->get()->keyBy('odoo_mo_id');
        $existingUsages = MaterialUsage::whereIn('weekly_plan_id', $weeklyPlans->pluck('id'))->get()->keyBy('weekly_plan_id');

        $result = [];
        foreach ($movesByMo as $odooMoId => $moMoves) {
            $plan = $weeklyPlans->get($odooMoId);
            $moInfo = $odooMos[$odooMoId] ?? null;

            $moName = is_array($moMoves[0]['raw_material_production_id'] ?? null)
                ? $moMoves[0]['raw_material_production_id'][1]
                : ($moInfo['name'] ?? ('MO #'.$odooMoId));

            $productName = $plan?->produk?->nama_produk
                ?? (is_array($moInfo['product_id'] ?? null) ? $moInfo['product_id'][1] : 'Produk Odoo');
            $productCode = $plan?->produk?->kode_produk ?? '-';
            $targetOutput = $plan?->target_output ?? (float) ($moInfo['product_qty'] ?? 0);
            $batchNumber = $plan?->batch_number
                ?? (is_array($moInfo['lot_producing_id'] ?? null) ? $moInfo['lot_producing_id'][1] : '-');

            $items = [];
            $totalQtyStd = 0;
            $totalQtyUsed = 0;

            foreach ($moMoves as $m) {
                $matName = is_array($m['product_id'] ?? null) ? $m['product_id'][1] : 'Material';
                $matOdooId = is_array($m['product_id'] ?? null) ? (int) $m['product_id'][0] : null;
                $qtyUsed = (float) ($m['quantity'] ?? $m['product_uom_qty'] ?? 0);
                $qtyStd = (float) ($m['product_uom_qty'] ?? $qtyUsed);
                $uomName = is_array($m['product_uom'] ?? null) ? $m['product_uom'][1] : null;

                $totalQtyStd += $qtyStd;
                $totalQtyUsed += $qtyUsed;

                $items[] = [
                    'material_name' => $matName,
                    'odoo_product_id' => $matOdooId,
                    'quantity_used' => $qtyUsed,
                    'quantity_standard' => $qtyStd,
                    'variance' => round($qtyUsed - $qtyStd, 4),
                    'ratio_persen' => $qtyStd > 0 ? round((($qtyUsed - $qtyStd) / $qtyStd) * 100, 2) : 0,
                    'uom' => $uomName,
                ];
            }

            $overallDiff = $totalQtyUsed - $totalQtyStd;
            $overallRatio = $totalQtyStd > 0 ? round(($overallDiff / $totalQtyStd) * 100, 2) : 0;

            $result[] = [
                'odoo_mo_id' => $odooMoId,
                'mo_name' => $moName,
                'plan_exists' => $plan !== null,
                'batch_number' => $batchNumber,
                'product_name' => $productName,
                'product_code' => $productCode,
                'target_output' => $targetOutput,
                'item_count' => count($items),
                'total_qty_standard' => round($totalQtyStd, 4),
                'total_qty_used' => round($totalQtyUsed, 4),
                'overall_ratio' => $overallRatio,
                'usage_exists' => $plan ? $existingUsages->has($plan->id) : false,
                'can_sync' => $plan !== null,
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * Pull dan sync Material Usage dari Odoo (stock.move raw materials).
     *
     * @param  array<int>|null  $selectedMoIds
     * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function syncMaterialUsages(?array $selectedMoIds = null): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $domain = [
                ['raw_material_production_id', '!=', false],
                ['state', 'in', ['done', 'progress']],
            ];

            if (! empty($selectedMoIds)) {
                $selectedMoIds = array_values(array_unique(array_map('intval', $selectedMoIds)));
                $domain[] = ['raw_material_production_id', 'in', $selectedMoIds];
            }

            $moves = $this->searchRead('stock.move', $domain, [
                'id', 'raw_material_production_id', 'product_id', 'quantity', 'product_uom_qty', 'product_uom', 'date',
            ], 1000);

            if (empty($moves)) {
                return $summary;
            }

            $movesByMo = [];
            foreach ($moves as $m) {
                $moId = is_array($m['raw_material_production_id']) ? (int) $m['raw_material_production_id'][0] : (int) $m['raw_material_production_id'];
                $movesByMo[$moId][] = $m;
            }

            $uomByCode = ScmUom::pluck('id', 'code');
            $uomByName = ScmUom::pluck('id', 'name');

            foreach ($movesByMo as $odooMoId => $moMoves) {
                $weeklyPlan = WeeklyPlan::where('odoo_mo_id', $odooMoId)->first();
                if (! $weeklyPlan) {
                    $summary['skipped']++;

                    continue;
                }

                $usageNumber = 'USG-ODOO-MO-'.$odooMoId;
                $usageDate = ! empty($moMoves[0]['date']) ? substr($moMoves[0]['date'], 0, 10) : ($weeklyPlan->tanggal ?: now()->toDateString());

                DB::transaction(function () use ($weeklyPlan, $usageNumber, $usageDate, $moMoves, $uomByCode, $uomByName, &$summary) {
                    $usage = MaterialUsage::updateOrCreate(
                        [
                            'weekly_plan_id' => $weeklyPlan->id,
                        ],
                        [
                            'usage_number' => $usageNumber,
                            'user_id' => $this->fallbackCreatorId(),
                            'usage_date' => $usageDate,
                            'shift' => 1,
                            'notes' => "Sync pemakaian komponen dari Odoo MO #{$weeklyPlan->odoo_mo_id}",
                        ]
                    );

                    $usage->items()->delete();

                    foreach ($moMoves as $m) {
                        $matName = is_array($m['product_id'] ?? null) ? $m['product_id'][1] : 'Material';
                        $matOdooId = is_array($m['product_id'] ?? null) ? (int) $m['product_id'][0] : null;
                        $qtyUsed = (float) ($m['quantity'] ?? $m['product_uom_qty'] ?? 0);
                        $qtyStd = (float) ($m['product_uom_qty'] ?? $qtyUsed);
                        $uomName = is_array($m['product_uom'] ?? null) ? $m['product_uom'][1] : null;

                        $matProduk = $matOdooId ? Produk::where('odoo_id', $matOdooId)->first() : null;

                        $uomId = null;
                        if ($uomName) {
                            $uomId = $uomByCode->get(strtoupper($uomName))
                                ?? $uomByName->get($uomName)
                                ?? $uomByCode->get(strtolower($uomName));
                        }

                        $usage->items()->create([
                            'produk_id' => $matProduk?->id,
                            'material_name' => $matName,
                            'quantity_used' => $qtyUsed,
                            'quantity_standard' => $qtyStd,
                            'variance' => $qtyUsed - $qtyStd,
                            'uom_id' => $uomId,
                        ]);
                    }

                    if ($usage->wasRecentlyCreated) {
                        $summary['created']++;
                    } else {
                        $summary['updated']++;
                    }
                });
            }
        } catch (Exception $e) {
            $summary['errors'][] = 'Material Usage Sync: '.$e->getMessage();
        }

        return $summary;
    }

    /**
     * Pull dan sync data Reject (Reject QA, Reject Supplier, Loss, Scrap) dari MO DONE dan Scrap Raw Material Odoo.
     *
     * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function syncMoRejects(int $limit = 5000): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            // 1. Ambil Stock Move (Material/Komponen Reject) HANYA dari MO berstatus DONE
            $moves = $this->searchRead('stock.move', [
                ['raw_material_production_id', '!=', false],
                ['raw_material_production_id.state', '=', 'done'],
                ['state', '=', 'done'],
                '|', '|',
                ['x_studio_reject_ga', '>', 0],
                ['x_studio_reject_sup', '>', 0],
                ['x_studio_loss', '>', 0],
            ], [
                'id', 'raw_material_production_id', 'product_id', 'date',
                'x_studio_reject_ga', 'x_studio_reject_sup', 'x_studio_loss',
                'x_studio_usage', 'quantity', 'product_uom',
            ], $limit);

            // 2. Ambil Stock Scrap (Scrap Orders) HANYA yang berstatus DONE (baik dari MO maupun Raw Material/QC/R&D)
            $scraps = $this->searchRead('stock.scrap', [
                ['state', '=', 'done'],
                ['scrap_qty', '>', 0],
            ], [
                'id', 'name', 'product_id', 'scrap_qty', 'product_uom_id', 'origin',
                'state', 'date_done', 'create_date', 'lot_id', 'production_id',
            ], $limit);

            if (empty($moves) && empty($scraps)) {
                return $summary;
            }

            // Pisahkan scraps yang ber-MO dan yang standalone (Raw Material Gudang/QC/R&D)
            $moIds = [];
            $movesByMo = [];
            foreach ($moves as $m) {
                $moId = is_array($m['raw_material_production_id'])
                    ? (int) $m['raw_material_production_id'][0]
                    : (int) $m['raw_material_production_id'];
                $movesByMo[$moId][] = $m;
                $moIds[$moId] = true;
            }

            $scrapsByMo = [];
            $standaloneScraps = [];
            foreach ($scraps as $s) {
                if (! empty($s['production_id'])) {
                    $moId = is_array($s['production_id'])
                        ? (int) $s['production_id'][0]
                        : (int) $s['production_id'];
                    $scrapsByMo[$moId][] = $s;
                    $moIds[$moId] = true;
                } else {
                    $standaloneScraps[] = $s;
                }
            }

            $moIdList = array_keys($moIds);
            $mos = [];
            if (! empty($moIdList)) {
                $chunkedMoIds = array_chunk($moIdList, 500);
                foreach ($chunkedMoIds as $chunk) {
                    $fetched = $this->searchRead('mrp.production', [
                        ['id', 'in', $chunk],
                        ['state', '=', 'done'],
                    ], [
                        'id', 'name', 'product_id', 'lot_producing_id', 'origin', 'date_start', 'state',
                        'x_studio_reject',
                    ], count($chunk));
                    foreach ($fetched as $mo) {
                        $mos[(int) $mo['id']] = $mo;
                    }
                }
            }

            $creatorId = $this->fallbackCreatorId();

            DB::transaction(function () use ($movesByMo, $scrapsByMo, $standaloneScraps, $mos, $creatorId, &$summary) {
                $allMoIds = array_unique(array_merge(array_keys($movesByMo), array_keys($scrapsByMo)));

                // A & B: Sync Rejects dari MO (stock.move & stock.scrap terkait MO)
                foreach ($allMoIds as $moId) {
                    $moData = $mos[$moId] ?? null;
                    if (! $moData) {
                        $summary['skipped']++;

                        continue;
                    }

                    $moName = $moData['name'] ?? ('MO #'.$moId);
                    $batchNumber = $this->parseBatchFromMo($moData);
                    $moDate = $this->parseMoDate($moData);
                    $moProdId = is_array($moData['product_id'] ?? null) ? (int) $moData['product_id'][0] : null;

                    $localProduk = $moProdId ? Produk::where('odoo_id', $moProdId)->first() : null;

                    $weeklyPlan = WeeklyPlan::withTrashed()->where('odoo_mo_id', $moId)->first();
                    if (! $weeklyPlan && $localProduk) {
                        $weeklyPlan = WeeklyPlan::create([
                            'produk_id' => $localProduk->id,
                            'proses' => $localProduk->proses_default ?: 'packing',
                            'batch_number' => $batchNumber,
                            'odoo_mo_id' => $moId,
                            'mo_status' => 'done',
                            'target_output' => 0,
                            'tanggal' => $moDate,
                            'status' => 'draft',
                            'created_by' => $creatorId,
                        ]);
                    }

                    $laporan = LaporanHarian::where('batch_number', $batchNumber)->first();
                    if (! $laporan && $weeklyPlan) {
                        $defaultMesinId = Mesin::orderBy('id')->value('id') ?? 1;
                        $defaultLineId = Line::orderBy('id')->value('id') ?? 1;
                        $prosesVal = in_array(strtolower($weeklyPlan->proses), ['mixing', 'filling', 'packing'], true)
                            ? strtolower($weeklyPlan->proses)
                            : 'packing';

                        $laporan = LaporanHarian::create([
                            'user_id' => $creatorId,
                            'weekly_plan_id' => $weeklyPlan->id,
                            'produk_id' => $weeklyPlan->produk_id,
                            'proses' => $prosesVal,
                            'batch_number' => $batchNumber,
                            'mesin_id' => $weeklyPlan->mesin_id ?? $defaultMesinId,
                            'ct' => 0,
                            'line_id' => $weeklyPlan->line_id ?? $defaultLineId,
                            'tanggal' => $weeklyPlan->tanggal ?: $moDate,
                            'shift' => 1,
                            'target_mp' => 0,
                            'total_mp' => 1,
                            'start_time' => '08:00:00',
                            'end_time' => '16:00:00',
                            'gross_time_menit' => 480,
                            'output_fisik' => (int) ($weeklyPlan->target_output ?: 1000),
                            'capacity_fisik' => (int) ($weeklyPlan->target_output ?: 1000),
                            'status' => 'draft',
                        ]);
                    }

                    if (! $laporan) {
                        $summary['skipped']++;

                        continue;
                    }

                    // A. Sync Moves (Komponen Material Rejects)
                    $moMoves = $movesByMo[$moId] ?? [];
                    foreach ($moMoves as $m) {
                        $moveId = (int) $m['id'];
                        $matName = is_array($m['product_id'] ?? null) ? $m['product_id'][1] : 'Material';
                        $matUom = is_array($m['product_uom'] ?? null) ? $m['product_uom'][1] : 'Pcs';
                        $rejectGa = (float) ($m['x_studio_reject_ga'] ?? 0);
                        $rejectSup = (float) ($m['x_studio_reject_sup'] ?? 0);
                        $loss = (float) ($m['x_studio_loss'] ?? 0);

                        if ($rejectGa > 0) {
                            $detail = RejectDetail::updateOrCreate(
                                [
                                    'laporan_harian_id' => $laporan->id,
                                    'odoo_scrap_id' => $moveId * 10 + 1,
                                ],
                                [
                                    'odoo_mo_id' => $moId,
                                    'odoo_mo_name' => $moName,
                                    'material_name' => $matName,
                                    'material_uom' => $matUom,
                                    'jenis_reject' => 'ga',
                                    'jumlah' => $rejectGa,
                                    'keterangan' => "[Odoo {$moName}] {$matName} — {$rejectGa} {$matUom} — Reject QA/GA",
                                    'created_by' => $creatorId,
                                    'odoo_synced_at' => now(),
                                ]
                            );
                            if ($detail->wasRecentlyCreated) {
                                $summary['created']++;
                            } else {
                                $summary['updated']++;
                            }
                        }

                        if ($rejectSup > 0) {
                            $detail = RejectDetail::updateOrCreate(
                                [
                                    'laporan_harian_id' => $laporan->id,
                                    'odoo_scrap_id' => $moveId * 10 + 2,
                                ],
                                [
                                    'odoo_mo_id' => $moId,
                                    'odoo_mo_name' => $moName,
                                    'material_name' => $matName,
                                    'material_uom' => $matUom,
                                    'jenis_reject' => 'sublayer',
                                    'jumlah' => $rejectSup,
                                    'keterangan' => "[Odoo {$moName}] {$matName} — {$rejectSup} {$matUom} — Reject Supplier",
                                    'created_by' => $creatorId,
                                    'odoo_synced_at' => now(),
                                ]
                            );
                            if ($detail->wasRecentlyCreated) {
                                $summary['created']++;
                            } else {
                                $summary['updated']++;
                            }
                        }

                        if ($loss > 0) {
                            $detail = RejectDetail::updateOrCreate(
                                [
                                    'laporan_harian_id' => $laporan->id,
                                    'odoo_scrap_id' => $moveId * 10 + 3,
                                ],
                                [
                                    'odoo_mo_id' => $moId,
                                    'odoo_mo_name' => $moName,
                                    'material_name' => $matName,
                                    'material_uom' => $matUom,
                                    'jenis_reject' => 'process',
                                    'jumlah' => $loss,
                                    'keterangan' => "[Odoo {$moName}] {$matName} — {$loss} {$matUom} — Material Loss",
                                    'created_by' => $creatorId,
                                    'odoo_synced_at' => now(),
                                ]
                            );
                            if ($detail->wasRecentlyCreated) {
                                $summary['created']++;
                            } else {
                                $summary['updated']++;
                            }
                        }
                    }

                    // B. Sync Scraps terkait MO
                    $moScraps = $scrapsByMo[$moId] ?? [];
                    foreach ($moScraps as $s) {
                        $scrapId = (int) $s['id'];
                        $scrapName = $s['name'] ?? ('SP/'.$scrapId);
                        $matName = is_array($s['product_id'] ?? null) ? $s['product_id'][1] : 'Material Scrap';
                        $matUom = is_array($s['product_uom_id'] ?? null) ? $s['product_uom_id'][1] : 'Pcs';
                        $scrapQty = (float) ($s['scrap_qty'] ?? 0);

                        if ($scrapQty > 0) {
                            $detail = RejectDetail::updateOrCreate(
                                [
                                    'odoo_scrap_id' => 9000000 + $scrapId,
                                ],
                                [
                                    'laporan_harian_id' => $laporan->id,
                                    'odoo_mo_id' => $moId,
                                    'odoo_mo_name' => $moName,
                                    'material_name' => $matName,
                                    'material_uom' => $matUom,
                                    'jenis_reject' => 'sublayer',
                                    'jumlah' => $scrapQty,
                                    'keterangan' => "[Odoo Scrap {$scrapName}] {$matName} — {$scrapQty} {$matUom} — Scrap Order",
                                    'created_by' => $creatorId,
                                    'odoo_synced_at' => now(),
                                ]
                            );
                            if ($detail->wasRecentlyCreated) {
                                $summary['created']++;
                            } else {
                                $summary['updated']++;
                            }
                        }
                    }
                }

                // C. Sync Standalone Raw Material Scraps (Scrap Gudang, Sample QC, R&D, dll)
                foreach ($standaloneScraps as $s) {
                    $scrapId = (int) $s['id'];
                    $scrapName = $s['name'] ?? ('SP/'.$scrapId);
                    $matName = is_array($s['product_id'] ?? null) ? (string) $s['product_id'][1] : 'Raw Material';
                    $matOdooId = is_array($s['product_id'] ?? null) ? (int) $s['product_id'][0] : null;
                    $matUom = is_array($s['product_uom_id'] ?? null) ? (string) $s['product_uom_id'][1] : 'Pcs';
                    $scrapQty = (float) ($s['scrap_qty'] ?? 0);
                    if ($scrapQty <= 0) {
                        continue;
                    }

                    $origin = ! empty($s['origin']) ? (string) $s['origin'] : 'Scrap Bahan Baku';
                    $lotName = is_array($s['lot_id'] ?? null) ? (string) $s['lot_id'][1] : null;
                    $batchNumber = $lotName ?: ($origin ?: ('SCRAP-'.$scrapId));
                    $dateDone = $s['date_done'] ?: ($s['create_date'] ?? now());
                    $parsedDate = date('Y-m-d', strtotime((string) $dateDone));

                    // 1. Resolve / Create local Produk
                    $localProduk = null;
                    if ($matOdooId) {
                        $localProduk = Produk::where('odoo_id', $matOdooId)->first();
                    }
                    if (! $localProduk) {
                        $localProduk = Produk::where('nama_produk', $matName)->first();
                    }
                    if (! $localProduk) {
                        $localProduk = Produk::create([
                            'odoo_id' => $matOdooId,
                            'kode_produk' => 'RM-'.($matOdooId ?: $scrapId),
                            'nama_produk' => $matName,
                            'proses_default' => 'mixing',
                            'odoo_uom' => $matUom,
                            'item_type' => 'rm',
                            'status_aktif' => true,
                        ]);
                    }

                    // 2. Resolve / Create WeeklyPlan
                    $weeklyPlan = WeeklyPlan::where('batch_number', $batchNumber)->where('produk_id', $localProduk->id)->first();
                    if (! $weeklyPlan) {
                        $weeklyPlan = WeeklyPlan::create([
                            'produk_id' => $localProduk->id,
                            'proses' => 'mixing',
                            'batch_number' => $batchNumber,
                            'mo_status' => 'done',
                            'target_output' => 0,
                            'tanggal' => $parsedDate,
                            'status' => 'draft',
                            'created_by' => $creatorId,
                        ]);
                    }

                    // 3. Resolve / Create LaporanHarian
                    $laporan = LaporanHarian::where('batch_number', $batchNumber)->where('produk_id', $localProduk->id)->first();
                    if (! $laporan) {
                        $defaultMesinId = Mesin::orderBy('id')->value('id') ?? 1;
                        $defaultLineId = Line::orderBy('id')->value('id') ?? 1;

                        $laporan = LaporanHarian::create([
                            'user_id' => $creatorId,
                            'weekly_plan_id' => $weeklyPlan->id,
                            'produk_id' => $localProduk->id,
                            'proses' => 'mixing',
                            'batch_number' => $batchNumber,
                            'mesin_id' => $defaultMesinId,
                            'ct' => 0,
                            'line_id' => $defaultLineId,
                            'tanggal' => $parsedDate,
                            'shift' => 1,
                            'target_mp' => 0,
                            'total_mp' => 1,
                            'start_time' => '08:00:00',
                            'end_time' => '16:00:00',
                            'gross_time_menit' => 480,
                            'output_fisik' => (int) round($scrapQty),
                            'capacity_fisik' => (int) round($scrapQty),
                            'status' => 'draft',
                        ]);
                    }

                    $jenisReject = 'sublayer';
                    if (stripos($origin, 'QC') !== false) {
                        $jenisReject = 'ga';
                    } elseif (stripos($origin, 'R&D') !== false) {
                        $jenisReject = 'process';
                    }

                    $detail = RejectDetail::updateOrCreate(
                        [
                            'odoo_scrap_id' => 9000000 + $scrapId,
                        ],
                        [
                            'laporan_harian_id' => $laporan->id,
                            'odoo_mo_id' => null,
                            'odoo_mo_name' => "[Scrap] {$origin}",
                            'material_name' => $matName,
                            'material_uom' => $matUom,
                            'jenis_reject' => $jenisReject,
                            'jumlah' => $scrapQty,
                            'keterangan' => "[Odoo Scrap {$scrapName}] {$matName} — {$scrapQty} {$matUom} — {$origin}",
                            'created_by' => $creatorId,
                            'odoo_synced_at' => now(),
                        ]
                    );

                    if ($detail->wasRecentlyCreated) {
                        $summary['created']++;
                    } else {
                        $summary['updated']++;
                    }
                }
            });
        } catch (Exception $e) {
            $summary['errors'][] = 'MO Reject Sync: '.$e->getMessage();
        }

        return $summary;
    }

    /**
     * Ambil rincian formula / breakdown bahan baku penyusun untuk item ruahan/primer/WIP.
     *
     * @return array<string, mixed>|null
     */
    public function getMaterialRecipeBreakdown(string $materialName, float $outputQty = 1.0, ?string $parentProductName = null): ?array
    {
        $materialClean = trim($materialName);
        $parentClean = $parentProductName ? trim($parentProductName) : '';

        // Extract base product keyword (e.g. Eyebost, Vitasma, Waji, Vitameal, Diabalance, dll)
        $keywords = array_filter(explode(' ', $materialClean.' '.$parentClean));
        $stopWords = ['primer', 'ruahan', 'new', 'pro', 'maklon', 'pcs', 'gr', 'ml', '135', '105', 'active', 'balance', 'less', 'sugar'];
        $baseName = '';
        foreach ($keywords as $kw) {
            if (! in_array(strtolower($kw), $stopWords, true) && strlen($kw) >= 3) {
                $baseName = $kw;
                break;
            }
        }
        if (empty($baseName)) {
            $baseName = $materialClean;
        }

        // Cache all Odoo BOMs and lines for high performance
        $allBoms = Cache::remember('odoo_all_boms_breakdown_v1', 1800, function () {
            return $this->searchRead('mrp.bom', [], [
                'id', 'product_tmpl_id', 'product_qty', 'bom_line_ids', 'code',
            ], 500);
        });

        if (empty($allBoms)) {
            return null;
        }

        // Find Ruahan BOM & Primer BOM
        $ruahanBom = null;
        $primerBom = null;

        foreach ($allBoms as $b) {
            $tmplName = is_array($b['product_tmpl_id'] ?? null) ? (string) $b['product_tmpl_id'][1] : '';
            $tmplLower = strtolower($tmplName);
            $baseLower = strtolower($baseName);

            if (str_contains($tmplLower, $baseLower)) {
                if (str_contains($tmplLower, 'ruahan') && ! $ruahanBom) {
                    $ruahanBom = $b;
                } elseif (str_contains($tmplLower, 'primer') && ! $primerBom) {
                    $primerBom = $b;
                }
            }
        }

        if (! $ruahanBom && ! $primerBom) {
            return null;
        }

        // Collect all needed line IDs
        $neededLineIds = array_merge(
            $ruahanBom['bom_line_ids'] ?? [],
            $primerBom['bom_line_ids'] ?? []
        );

        if (empty($neededLineIds)) {
            return null;
        }

        $allLines = Cache::remember('odoo_bom_lines_all_v1', 1800, function () {
            return $this->searchRead('mrp.bom.line', [], [
                'id', 'bom_id', 'product_id', 'product_qty', 'product_uom_id',
            ], 2000);
        });

        $linesByBom = [];
        foreach ($allLines as $line) {
            $bId = is_array($line['bom_id'] ?? null) ? (int) $line['bom_id'][0] : (int) ($line['bom_id'] ?? 0);
            $linesByBom[$bId][] = $line;
        }

        // 1. Determine Ruahan dosage per finished good unit (default 160g if liquid syrup bottle, or from Primer BOM)
        $ruahanPerUnitGrams = 0.0;
        $primerPackaging = [];

        if ($primerBom) {
            $primerBomId = (int) $primerBom['id'];
            $pLines = $linesByBom[$primerBomId] ?? [];
            foreach ($pLines as $pl) {
                $pName = is_array($pl['product_id'] ?? null) ? (string) $pl['product_id'][1] : 'Komponen';
                $pQty = (float) ($pl['product_qty'] ?? 0);
                $pUom = is_array($pl['product_uom_id'] ?? null) ? (string) $pl['product_uom_id'][1] : 'Pcs';

                if (str_contains(strtolower($pName), 'ruahan')) {
                    $ruahanPerUnitGrams = $pQty; // usually in grams e.g. 160g
                } else {
                    $primerPackaging[] = [
                        'name' => $pName,
                        'qty_per_unit' => $pQty,
                        'total_batch_qty' => round($pQty * $outputQty, 2),
                        'uom' => $pUom,
                    ];
                }
            }
        }

        if ($ruahanPerUnitGrams <= 0) {
            $ruahanPerUnitGrams = 160.0; // Standard fallback liquid dose 160g / unit
        }

        // 2. Process Ruahan Raw Materials
        $rawMaterials = [];
        $totalRuahanBatchGrams = $ruahanPerUnitGrams * $outputQty;
        $totalRuahanBatchKg = $totalRuahanBatchGrams / 1000.0;

        if ($ruahanBom) {
            $ruahanBomId = (int) $ruahanBom['id'];
            $rLines = $linesByBom[$ruahanBomId] ?? [];
            $totalFormulaGrams = array_sum(array_map(fn ($l) => (float) ($l['product_qty'] ?? 0), $rLines));
            if ($totalFormulaGrams <= 0) {
                $totalFormulaGrams = 1000.0; // 1000g basis
            }

            foreach ($rLines as $rl) {
                $rmName = is_array($rl['product_id'] ?? null) ? (string) $rl['product_id'][1] : 'Bahan Baku';
                $rmQty = (float) ($rl['product_qty'] ?? 0);
                $percentage = ($rmQty / $totalFormulaGrams) * 100.0;

                // Total needed for this batch
                $batchGrams = ($percentage / 100.0) * $totalRuahanBatchGrams;
                $batchKg = $batchGrams / 1000.0;
                $qtyPerUnitGrams = ($percentage / 100.0) * $ruahanPerUnitGrams;

                $rawMaterials[] = [
                    'name' => $rmName,
                    'percentage' => round($percentage, 2),
                    'qty_per_unit_g' => round($qtyPerUnitGrams, 3),
                    'total_kg' => round($batchKg, 2),
                    'total_g' => round($batchGrams, 1),
                    'display_qty' => $batchKg >= 1.0 ? number_format($batchKg, 2, ',', '.').' Kg' : number_format($batchGrams, 1, ',', '.').' g',
                ];
            }
        }

        return [
            'is_ruahan' => true,
            'base_product_name' => $baseName,
            'ruahan_bom_name' => is_array($ruahanBom['product_tmpl_id'] ?? null) ? $ruahanBom['product_tmpl_id'][1] : ($baseName.' Ruahan'),
            'ruahan_dose_per_unit_g' => $ruahanPerUnitGrams,
            'total_batch_ruahan_kg' => round($totalRuahanBatchKg, 2),
            'total_batch_ruahan_g' => round($totalRuahanBatchGrams, 0),
            'target_output_units' => $outputQty,
            'raw_materials_count' => count($rawMaterials),
            'raw_materials' => $rawMaterials,
            'primer_packaging' => $primerPackaging,
        ];
    }

    /**
     * Fetch BOMs preview from Odoo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchBomsPreview(): array
    {
        $odooBoms = $this->searchRead('mrp.bom', [], [
            'id', 'product_tmpl_id', 'product_id', 'product_qty', 'product_uom_id', 'code', 'type', 'bom_line_ids',
        ], 500);

        if (empty($odooBoms)) {
            return [];
        }

        // Collect all line IDs
        $allLineIds = [];
        foreach ($odooBoms as $b) {
            if (! empty($b['bom_line_ids'])) {
                $allLineIds = array_merge($allLineIds, $b['bom_line_ids']);
            }
        }
        $allLineIds = array_values(array_unique($allLineIds));

        $lineMap = [];
        $chunks = array_chunk($allLineIds, 200);
        foreach ($chunks as $chk) {
            $lines = $this->searchRead('mrp.bom.line', [['id', 'in', $chk]], [
                'id', 'bom_id', 'product_id', 'product_qty', 'product_uom_id',
            ], count($chk));
            foreach ($lines as $l) {
                $lineMap[$l['id']] = $l;
            }
        }

        $existingBomMap = Bom::pluck('id', 'odoo_bom_id')->toArray();
        $existingProductBoms = Bom::pluck('id', 'produk_id')->toArray();

        $result = [];
        foreach ($odooBoms as $b) {
            $odooBomId = (int) $b['id'];
            $pId = is_array($b['product_id'] ?? null) ? (int) $b['product_id'][0] : (is_array($b['product_tmpl_id'] ?? null) ? (int) $b['product_tmpl_id'][0] : null);
            $pName = is_array($b['product_id'] ?? null) ? $b['product_id'][1] : (is_array($b['product_tmpl_id'] ?? null) ? $b['product_tmpl_id'][1] : 'Produk Odoo');
            $uomName = is_array($b['product_uom_id'] ?? null) ? $b['product_uom_id'][1] : 'Pcs';
            $baseQty = (float) ($b['product_qty'] ?? 1.0);
            $code = ! empty($b['code']) ? (string) $b['code'] : 'Odoo-v1';

            $items = [];
            foreach ($b['bom_line_ids'] ?? [] as $lineId) {
                $line = $lineMap[$lineId] ?? null;
                if (! $line) {
                    continue;
                }
                $matName = is_array($line['product_id'] ?? null) ? $line['product_id'][1] : 'Material';
                $matQty = (float) ($line['product_qty'] ?? 0);
                $matUom = is_array($line['product_uom_id'] ?? null) ? $line['product_uom_id'][1] : 'Pcs';
                $items[] = [
                    'material_name' => $matName,
                    'quantity' => $matQty,
                    'uom' => $matUom,
                    'category' => Bom::detectCategory($matName),
                ];
            }

            $localProduk = null;
            if ($pId) {
                $localProduk = Produk::where('odoo_id', $pId)->orWhere('nama_produk', $pName)->first();
            }

            $exists = isset($existingBomMap[$odooBomId]) || ($localProduk && isset($existingProductBoms[$localProduk->id]));

            $result[] = [
                'odoo_bom_id' => $odooBomId,
                'product_name' => $pName,
                'product_id' => $pId,
                'code' => $code,
                'base_qty' => $baseQty,
                'uom' => $uomName,
                'lines_count' => count($items),
                'items' => $items,
                'exists_locally' => $exists,
            ];
        }

        return $result;
    }

    /**
     * Sync BOMs from Odoo into local boms and bom_items tables.
     *
     * @param  array<int>|null  $selectedIds
     * @return array<string, mixed>
     */
    public function syncBoms(?array $selectedIds = null): array
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            // 1. Ensure UOMs exist in scm_uoms
            $odooUoms = $this->searchRead('uom.uom', [], ['id', 'name', 'category_id'], 200);
            foreach ($odooUoms as $ou) {
                if (! empty($ou['name'])) {
                    ScmUom::firstOrCreate(
                        ['code' => $ou['name']],
                        ['name' => $ou['name']]
                    );
                }
            }

            $domain = [];
            if (! empty($selectedIds)) {
                $domain[] = ['id', 'in', $selectedIds];
            }

            $odooBoms = $this->searchRead('mrp.bom', $domain, [
                'id', 'product_tmpl_id', 'product_id', 'product_qty', 'product_uom_id', 'code', 'type', 'bom_line_ids',
            ], 500);

            if (empty($odooBoms)) {
                return $summary;
            }

            // Collect line IDs
            $allLineIds = [];
            foreach ($odooBoms as $b) {
                if (! empty($b['bom_line_ids'])) {
                    $allLineIds = array_merge($allLineIds, $b['bom_line_ids']);
                }
            }
            $allLineIds = array_values(array_unique($allLineIds));

            $lineMap = [];
            $chunks = array_chunk($allLineIds, 200);
            foreach ($chunks as $chk) {
                $lines = $this->searchRead('mrp.bom.line', [['id', 'in', $chk]], [
                    'id', 'bom_id', 'product_id', 'product_qty', 'product_uom_id',
                ], count($chk));
                foreach ($lines as $l) {
                    $lineMap[$l['id']] = $l;
                }
            }

            $uomLookup = ScmUom::pluck('id', 'code')->toArray();

            foreach ($odooBoms as $b) {
                $odooBomId = (int) $b['id'];
                $pId = is_array($b['product_id'] ?? null) ? (int) $b['product_id'][0] : (is_array($b['product_tmpl_id'] ?? null) ? (int) $b['product_tmpl_id'][0] : null);
                $pName = is_array($b['product_id'] ?? null) ? $b['product_id'][1] : (is_array($b['product_tmpl_id'] ?? null) ? $b['product_tmpl_id'][1] : 'Produk Odoo');
                $uomName = is_array($b['product_uom_id'] ?? null) ? $b['product_uom_id'][1] : 'Pcs';
                $baseQty = (float) ($b['product_qty'] ?? 1.0);
                $version = ! empty($b['code']) ? (string) $b['code'] : 'Odoo-v1';

                if (! $pId) {
                    $summary['skipped']++;

                    continue;
                }

                $produk = Produk::where('odoo_id', $pId)->first();
                if (! $produk) {
                    $produk = Produk::where('nama_produk', $pName)->first();
                }
                if (! $produk) {
                    $itemType = 'fg';
                    if (stripos($pName, 'ruahan') !== false) {
                        $itemType = 'rm';
                    } elseif (stripos($pName, 'primer') !== false) {
                        $itemType = 'wip';
                    }

                    $code = 'OD-'.$pId;
                    $counter = 1;
                    while (Produk::where('kode_produk', $code)->exists()) {
                        $code = 'OD-'.$pId.'-'.$counter;
                        $counter++;
                    }

                    $produk = Produk::create([
                        'odoo_id' => $pId,
                        'kode_produk' => $code,
                        'nama_produk' => $pName,
                        'item_type' => $itemType,
                        'proses_default' => stripos($pName, 'ruahan') !== false ? 'mixing' : (stripos($pName, 'primer') !== false ? 'filling' : 'packing'),
                        'odoo_uom' => $uomName,
                        'status_aktif' => true,
                    ]);
                }

                $uomId = $uomLookup[$uomName] ?? null;

                // Deactivate any other BOM for this product if setting active
                Bom::where('produk_id', $produk->id)->where('odoo_bom_id', '!=', $odooBomId)->update(['is_active' => false]);

                $bom = Bom::where('odoo_bom_id', $odooBomId)
                    ->orWhere(fn ($q) => $q->where('produk_id', $produk->id)->where('is_active', true))
                    ->first();

                $isNew = false;
                if (! $bom) {
                    $bom = new Bom;
                    $isNew = true;
                }

                $bom->fill([
                    'produk_id' => $produk->id,
                    'odoo_bom_id' => $odooBomId,
                    'version' => $version,
                    'base_qty' => $baseQty,
                    'uom_id' => $uomId,
                    'is_active' => true,
                    'notes' => 'Synced from Odoo BOM #'.$odooBomId.' (Base: '.$baseQty.' '.$uomName.')',
                ]);
                $bom->save();

                $bom->items()->delete();

                foreach ($b['bom_line_ids'] ?? [] as $lineId) {
                    $line = $lineMap[$lineId] ?? null;
                    if (! $line) {
                        continue;
                    }

                    $matOdooId = is_array($line['product_id'] ?? null) ? (int) $line['product_id'][0] : null;
                    $matName = is_array($line['product_id'] ?? null) ? $line['product_id'][1] : 'Material';
                    $matQty = (float) ($line['product_qty'] ?? 0);
                    $matUomName = is_array($line['product_uom_id'] ?? null) ? $line['product_uom_id'][1] : null;
                    $matUomId = $matUomName ? ($uomLookup[$matUomName] ?? null) : null;
                    $category = Bom::detectCategory($matName);

                    $matProduk = null;
                    if ($matOdooId) {
                        $matProduk = Produk::where('odoo_id', $matOdooId)->first();
                        if (! $matProduk) {
                            $matProduk = Produk::where('nama_produk', $matName)->first();
                        }
                    }

                    $bom->items()->create([
                        'material_produk_id' => $matProduk?->id,
                        'material_name' => $matName,
                        'quantity' => $matQty,
                        'uom_id' => $matUomId,
                        'uom_name' => $matUomName,
                        'category' => $category,
                    ]);
                }

                if ($isNew) {
                    $summary['created']++;
                } else {
                    $summary['updated']++;
                }
            }
        } catch (Exception $e) {
            $summary['errors'][] = 'BOM Sync Error: '.$e->getMessage();
        }

        return $summary;
    }
}
