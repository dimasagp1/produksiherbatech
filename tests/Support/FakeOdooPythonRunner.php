<?php

namespace Tests\Support;

use App\Services\Odoo\OdooPythonRunner;

class FakeOdooPythonRunner extends OdooPythonRunner
{
    /** @var array<string, mixed>|null */
    public ?array $lastPayload = null;

    /** @var array<string, mixed> */
    public array $summary = [
        'success' => true,
        'host' => 'https://pt-herbal.odoo.com',
        'db' => 'pt-herbal',
        'server_version' => '18.0',
        'results' => [
            ['model' => 'res.partner', 'created' => 2, 'updated' => 0, 'skipped' => 0, 'errors' => []],
            ['model' => 'product.product', 'created' => 9, 'updated' => 0, 'skipped' => 0, 'errors' => []],
        ],
    ];

    public ?string $scriptOverride = null;

    public function scriptExists(): bool
    {
        return $this->scriptOverride !== null ? (bool) $this->scriptOverride : parent::scriptExists();
    }

    public function run(array $payload): array
    {
        $this->lastPayload = $payload;

        return $this->summary;
    }
}
