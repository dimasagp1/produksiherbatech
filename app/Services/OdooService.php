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
    public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 500, int $offset = 0): array
    {
        $kwargs = [
            'fields' => $fields,
            'limit' => $limit,
            'offset' => $offset,
        ];

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
     * Fetch Manufacturing Orders (mrp.production) from Odoo
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchManufacturingOrders(array $domain = []): array
    {
        $domain = $domain !== [] ? $domain : [
            ['state', 'in', ['confirmed', 'progress', 'done', 'cancel']],
        ];

        return $this->searchRead('mrp.production', $domain, [
            'id', 'name', 'origin', 'state',
            'product_id', 'product_qty', 'bom_id',
            'date_start', 'date_finished',
        ], 500);
    }

    /**
     * Sync Odoo Manufacturing Orders into local weekly_plans (manual button, on-demand).
     *
     * @return array{created:int,updated:int,cancelled:int,skipped:int,errors:array<int,string>}
     */
    public function syncManufacturingOrders(): array
    {
        $raw = $this->fetchManufacturingOrders();

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

        foreach ($raw as $item) {
            $odooMoId = (int) ($item['id'] ?? 0);
            if ($odooMoId <= 0) {
                continue;
            }

            $state = (string) ($item['state'] ?? 'confirmed');
            $moStatus = $stateMap[$state] ?? 'confirmed';
            $produkId = is_array($item['product_id'] ?? null) ? (int) $item['product_id'][0] : (int) ($item['product_id'] ?? 0);

            try {
                $plan = WeeklyPlan::withTrashed()->where('odoo_mo_id', $odooMoId)->first();

                if ($moStatus === 'cancelled') {
                    if ($plan && ! $plan->trashed()) {
                        $plan->applyOdooSync(['mo_status' => 'cancelled']);
                        $plan->delete();
                        $summary['cancelled']++;
                    } elseif ($plan) {
                        $summary['skipped']++;
                    } else {
                        $summary['skipped']++;
                    }

                    continue;
                }

                $batchNumber = $this->parseBatchFromMo($item);
                $tanggal = $this->parseMoDate($item);
                $produk = $produkId > 0 ? Produk::where('odoo_id', $produkId)->first() : null;

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
                    ]);
                    $summary['updated']++;

                    continue;
                }

                $duplicate = WeeklyPlan::withTrashed()
                    ->where('batch_number', $batchNumber)
                    ->where('tanggal', $tanggal)
                    ->exists();
                if ($duplicate) {
                    $summary['errors'][] = "MO {$item['name']}: batch {$batchNumber} tanggal {$tanggal} sudah ada di plan lain";
                    $summary['skipped']++;

                    continue;
                }

                WeeklyPlan::create([
                    'produk_id' => $produk->id,
                    'line_id' => null,
                    'proses' => $produk->proses_default ?: 'mixing',
                    'batch_number' => $batchNumber,
                    'odoo_mo_id' => $odooMoId,
                    'mo_status' => $moStatus,
                    'target_output' => $targetOutput,
                    'mp_count' => 0,
                    'multiplier' => $multiplier,
                    'packing_hold' => false,
                    'tanggal' => $tanggal,
                    'status' => 'draft',
                    'created_by' => $this->fallbackCreatorId(),
                ]);
                $summary['created']++;
            } catch (Exception $e) {
                $summary['errors'][] = "MO #{$odooMoId}: ".$e->getMessage();
            }
        }

        return $summary;
    }

    protected function parseBatchFromMo(array $item): string
    {
        $origin = trim((string) ($item['origin'] ?? ''));
        if ($origin !== '' && preg_match('/(?:Batch:\s*|LinePulse\s*-\s*)([A-Za-z0-9\-\/]+)/i', $origin, $matches)) {
            return trim($matches[1]);
        }
        if ($origin !== '') {
            return $origin;
        }

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
     * Pull dan sync BOM dari Odoo (mrp.bom & mrp.bom.line).
     *
     * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function syncBoms(): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $odooBoms = $this->searchRead('mrp.bom', [], [
                'id', 'product_tmpl_id', 'product_id', 'code', 'product_qty', 'bom_line_ids',
            ], 500);

            if (empty($odooBoms)) {
                return $summary;
            }

            $allLineIds = [];
            foreach ($odooBoms as $b) {
                if (! empty($b['bom_line_ids'])) {
                    $allLineIds = array_merge($allLineIds, $b['bom_line_ids']);
                }
            }

            $allLineIds = array_unique($allLineIds);
            $odooLines = [];
            if (! empty($allLineIds)) {
                $odooLines = $this->searchRead('mrp.bom.line', [['id', 'in', array_values($allLineIds)]], [
                    'id', 'bom_id', 'product_id', 'product_qty', 'product_uom_id',
                ], 2000);
            }

            $linesByBomId = [];
            foreach ($odooLines as $line) {
                $bomId = is_array($line['bom_id']) ? (int) $line['bom_id'][0] : (int) $line['bom_id'];
                $linesByBomId[$bomId][] = $line;
            }

            $uomByCode = ScmUom::pluck('id', 'code');
            $uomByName = ScmUom::pluck('id', 'name');

            foreach ($odooBoms as $odooBom) {
                $bomId = (int) $odooBom['id'];
                $tmplId = is_array($odooBom['product_tmpl_id'] ?? null) ? (int) $odooBom['product_tmpl_id'][0] : null;
                $tmplName = is_array($odooBom['product_tmpl_id'] ?? null) ? $odooBom['product_tmpl_id'][1] : null;

                $produk = null;
                if ($tmplId) {
                    $produk = Produk::where('odoo_id', $tmplId)->first();
                }
                if (! $produk && $tmplName) {
                    $produk = Produk::where('nama_produk', 'like', "%{$tmplName}%")->first();
                }

                if (! $produk) {
                    $summary['skipped']++;

                    continue;
                }

                $lines = $linesByBomId[$bomId] ?? [];
                if (empty($lines)) {
                    $summary['skipped']++;

                    continue;
                }

                $version = ! empty($odooBom['code']) ? (string) $odooBom['code'] : 'Odoo-v1';

                DB::transaction(function () use ($produk, $version, $bomId, $lines, $uomByCode, $uomByName, &$summary) {
                    Bom::where('produk_id', $produk->id)->where('is_active', true)->update(['is_active' => false]);

                    $bom = Bom::updateOrCreate(
                        [
                            'produk_id' => $produk->id,
                            'version' => $version,
                        ],
                        [
                            'is_active' => true,
                            'notes' => "Sync dari Odoo BOM #{$bomId}",
                            'created_by' => $this->fallbackCreatorId(),
                        ]
                    );

                    $bom->items()->delete();

                    foreach ($lines as $line) {
                        $matName = is_array($line['product_id'] ?? null) ? $line['product_id'][1] : 'Material';
                        $matOdooId = is_array($line['product_id'] ?? null) ? (int) $line['product_id'][0] : null;
                        $matQty = (float) ($line['product_qty'] ?? 0);
                        $uomName = is_array($line['product_uom_id'] ?? null) ? $line['product_uom_id'][1] : null;

                        $matProduk = $matOdooId ? Produk::where('odoo_id', $matOdooId)->first() : null;

                        $uomId = null;
                        if ($uomName) {
                            $uomId = $uomByCode->get(strtoupper($uomName))
                                ?? $uomByName->get($uomName)
                                ?? $uomByCode->get(strtolower($uomName));
                        }

                        $bom->items()->create([
                            'material_produk_id' => $matProduk?->id,
                            'material_name' => $matName,
                            'quantity' => $matQty,
                            'uom_id' => $uomId,
                        ]);
                    }

                    if ($bom->wasRecentlyCreated) {
                        $summary['created']++;
                    } else {
                        $summary['updated']++;
                    }
                });
            }
        } catch (Exception $e) {
            $summary['errors'][] = 'BOM Sync: '.$e->getMessage();
        }

        return $summary;
    }

    /**
     * Pull dan sync Saldo Stok dari Odoo (product.product qty_available) untuk FG, RM, PM, dan WIP.
     *
     * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function syncInventoryStocks(): array
    {
        $summary = ['updated' => 0, 'created' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $products = $this->searchRead('product.product', [['active', '=', true]], [
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
     * Pull dan sync Material Usage dari Odoo (stock.move raw materials).
     *
     * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function syncMaterialUsages(): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $moves = $this->searchRead('stock.move', [
                ['raw_material_production_id', '!=', false],
                ['state', 'in', ['done', 'progress']],
            ], [
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
     * Pull dan sync data Reject (Reject QA, Reject Supplier, Loss) dari MO Odoo yang sudah Done.
     *
     * @return array{created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function syncMoRejects(int $limit = 500): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $moves = $this->searchRead('stock.move', [
                ['raw_material_production_id', '!=', false],
                ['state', 'in', ['done', 'progress']],
                '|', '|',
                ['x_studio_reject_ga', '>', 0],
                ['x_studio_reject_sup', '>', 0],
                ['x_studio_loss', '>', 0],
            ], [
                'id', 'raw_material_production_id', 'product_id', 'date',
                'x_studio_reject_ga', 'x_studio_reject_sup', 'x_studio_loss',
                'x_studio_usage', 'quantity', 'product_uom',
            ], $limit);

            if (empty($moves)) {
                return $summary;
            }

            $movesByMo = [];
            foreach ($moves as $m) {
                $moId = is_array($m['raw_material_production_id'])
                    ? (int) $m['raw_material_production_id'][0]
                    : (int) $m['raw_material_production_id'];
                $movesByMo[$moId][] = $m;
            }

            $moIds = array_keys($movesByMo);
            $mos = $this->searchRead('mrp.production', [['id', 'in', $moIds]], [
                'id', 'name', 'product_id', 'lot_producing_id', 'origin', 'date_start', 'state',
                'x_studio_reject',
            ], count($moIds));

            $moById = [];
            foreach ($mos as $mo) {
                $moById[(int) $mo['id']] = $mo;
            }

            $creatorId = $this->fallbackCreatorId();

            DB::transaction(function () use ($movesByMo, $moById, $creatorId, &$summary) {
                foreach ($movesByMo as $moId => $moMoves) {
                    $moData = $moById[$moId] ?? null;
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
                            'mo_status' => $moData['state'] ?? 'done',
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

                    foreach ($moMoves as $m) {
                        $moveId = (int) $m['id'];
                        $matName = is_array($m['product_id'] ?? null) ? $m['product_id'][1] : 'Material';
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
                                    'jenis_reject' => 'ga',
                                    'jumlah' => (int) round($rejectGa),
                                    'keterangan' => "[Odoo {$moName}] {$matName} - Reject QA/GA",
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
                                    'jenis_reject' => 'sublayer',
                                    'jumlah' => (int) round($rejectSup),
                                    'keterangan' => "[Odoo {$moName}] {$matName} - Reject Supplier",
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
                                    'jenis_reject' => 'process',
                                    'jumlah' => (int) round($loss),
                                    'keterangan' => "[Odoo {$moName}] {$matName} - Material Loss",
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
            });
        } catch (Exception $e) {
            $summary['errors'][] = 'MO Reject Sync: '.$e->getMessage();
        }

        return $summary;
    }
}
