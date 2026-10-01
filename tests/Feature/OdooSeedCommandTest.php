<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Odoo\OdooPythonRunner;
use App\Services\Odoo\OdooSeedPayloadBuilder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\Support\FakeOdooPythonRunner;
use Tests\TestCase;

class OdooSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingSeeder)->run();
    }

    public function test_command_rejected_when_seed_disabled(): void
    {
        putenv('ODOO_SEED_ENABLED=0');
        $_ENV['ODOO_SEED_ENABLED'] = '0';
        $_SERVER['ODOO_SEED_ENABLED'] = '0';

        $this->app->instance(OdooPythonRunner::class, new FakeOdooPythonRunner);

        try {
            $this->artisan('odoo:seed', ['--only' => 'master', '--skip-connection-check' => true])
                ->expectsOutputToContain('dinonaktifkan')
                ->assertFailed();
        } finally {
            putenv('ODOO_SEED_ENABLED');
            unset($_ENV['ODOO_SEED_ENABLED'], $_SERVER['ODOO_SEED_ENABLED']);
        }
    }

    public function test_command_runs_dry_run_via_python_runner(): void
    {
        putenv('ODOO_SEED_ENABLED=1');
        $_ENV['ODOO_SEED_ENABLED'] = '1';

        $fake = new FakeOdooPythonRunner;
        $this->app->instance(OdooPythonRunner::class, $fake);

        // isi kredensial lokal via Setting (bukan env nyata)
        Setting::set('odoo_host', 'https://pt-herbal.odoo.com', 'odoo');
        Setting::set('odoo_db', 'pt-herbal', 'odoo');
        Setting::set('odoo_username', 'seed-test@example.com', 'odoo');
        Setting::set('odoo_api_key', 'fake-key-not-real', 'odoo');

        try {
            $this->artisan('odoo:seed', [
                '--only' => 'master',
                '--dry-run' => true,
                '--skip-connection-check' => true,
            ])->assertSuccessful();

            $this->assertNotNull($fake->lastPayload);
            $this->assertSame('master', $fake->lastPayload['only']);
            $this->assertTrue($fake->lastPayload['dry_run']);
            $this->assertFalse($fake->lastPayload['force']);
            $this->assertSame('https://pt-herbal.odoo.com', $fake->lastPayload['host']);
            $this->assertSame('pt-herbal', $fake->lastPayload['db']);
            // API key tidak boleh bocor ke argv — hanya di stdin payload
            $this->assertSame('fake-key-not-real', $fake->lastPayload['api_key']);
        } finally {
            putenv('ODOO_SEED_ENABLED');
            unset($_ENV['ODOO_SEED_ENABLED']);
        }
    }

    public function test_command_rejects_invalid_only_scope(): void
    {
        putenv('ODOO_SEED_ENABLED=1');

        try {
            $this->artisan('odoo:seed', ['--only' => 'bogus', '--skip-connection-check' => true])
                ->assertFailed();
        } finally {
            putenv('ODOO_SEED_ENABLED');
        }
    }

    public function test_payload_builder_dataset_mirrors_plan(): void
    {
        $dataset = app(OdooSeedPayloadBuilder::class)->dataset();

        $this->assertCount(2, $dataset['partners']);
        $this->assertCount(9, $dataset['products']);
        $this->assertCount(1, $dataset['boms']);
        $this->assertCount(3, $dataset['manufacturing_orders']);
        $this->assertCount(2, $dataset['scraps']);
        $this->assertCount(1, $dataset['sale_orders']);

        $codes = array_column($dataset['products'], 'default_code');
        $this->assertSame(['HB-001', 'HB-002', 'HB-003', 'RM-001', 'RM-002', 'RM-003', 'PM-001', 'PM-002', 'PM-003'], $codes);

        foreach ($dataset['manufacturing_orders'] as $mo) {
            $this->assertMatchesRegularExpression('/^Batch: BATCH-\d{3}$/', $mo['origin']);
        }
    }

    public function test_python_script_help_and_missing_credentials_contract(): void
    {
        $script = base_path('scripts/odoo/odoo_seed.py');
        $this->assertFileExists($script);

        $python = app(OdooPythonRunner::class)->pythonBinary();

        $help = Process::run([$python, $script, '--help']);
        $this->assertTrue($help->successful());
        $this->assertStringContainsString('--only', $help->output());

        // tanpa kredensial → JSON error jelas, exit 1 (kosongkan ODOO_* agar tidak inherit .env)
        $run = Process::env([
            'PATH' => (string) getenv('PATH'),
            'HOME' => (string) getenv('HOME'),
            'ODOO_HOST' => '',
            'ODOO_DB' => '',
            'ODOO_USERNAME' => '',
            'ODOO_API_KEY' => '',
        ])->run([$python, $script, '--only', 'master', '--dry-run']);
        $this->assertSame(1, $run->exitCode());
        $json = json_decode($run->output(), true);
        $this->assertIsArray($json);
        $this->assertFalse($json['success'] ?? true);
        $this->assertStringContainsString('Kredensial belum lengkap', (string) ($json['error'] ?? ''));
    }
}
