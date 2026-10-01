<?php

namespace App\Services\Odoo;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Jalankan scripts/odoo/odoo_seed.py (hybrid PHP + Python stdlib).
 * API key dikirim via stdin JSON — bukan argv.
 */
class OdooPythonRunner
{
    private string $scriptPath;

    public function __construct(?string $scriptPath = null)
    {
        $this->scriptPath = $scriptPath ?? self::defaultScriptPath();
    }

    public static function defaultScriptPath(): string
    {
        return base_path('scripts/odoo/odoo_seed.py');
    }

    public function scriptExists(): bool
    {
        return is_file($this->scriptPath);
    }

    public function pythonBinary(): string
    {
        foreach (['python3', 'python', 'py'] as $bin) {
            $proc = new Process([$bin, '--version']);
            $proc->run();
            if ($proc->isSuccessful()) {
                return $bin;
            }
        }

        throw new RuntimeException('python3 tidak ditemukan di PATH. Install Python 3 (stdlib only, tanpa pip).');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> JSON summary dari script
     */
    public function run(array $payload): array
    {
        if (! $this->scriptExists()) {
            throw new RuntimeException("Script seed tidak ditemukan: {$this->scriptPath}");
        }

        $python = $this->pythonBinary();
        $process = new Process([$python, $this->scriptPath]);
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->setTimeout((int) ($payload['timeout'] ?? 120));
        // api_key tidak boleh muncul di log
        $process->run();

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        if ($stdout === '') {
            throw new RuntimeException(
                'odoo_seed.py tidak mengembalikan JSON. stderr: '.($stderr !== '' ? $stderr : '(kosong)')
            );
        }

        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Output odoo_seed.py bukan JSON valid: '.substr($stdout, 0, 300));
        }

        if (! ($decoded['success'] ?? false)) {
            $msg = $decoded['error'] ?? ($stderr !== '' ? $stderr : 'Seed gagal');
            throw new RuntimeException((string) $msg);
        }

        return $decoded;
    }
}
