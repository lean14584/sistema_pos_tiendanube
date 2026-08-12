<?php

namespace Tests\Unit;

use App\Support\Backup\BackupService;
use PHPUnit\Framework\TestCase;

class BackupServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'backup-test-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_rotar_conserva_los_mas_nuevos_y_borra_los_viejos(): void
    {
        // 5 respaldos con fechas de modificación crecientes.
        for ($i = 1; $i <= 5; $i++) {
            $f = $this->dir.DIRECTORY_SEPARATOR.'respaldo-demo-'.$i.'.zip';
            file_put_contents($f, 'x');
            touch($f, now()->addMinutes($i)->timestamp);
        }

        $borrados = (new BackupService())->rotar($this->dir, 2);

        $this->assertSame(3, $borrados);
        $restantes = glob($this->dir.DIRECTORY_SEPARATOR.'respaldo-*.zip');
        $this->assertCount(2, $restantes);
        // Se conservan los dos más nuevos (4 y 5).
        $nombres = array_map('basename', $restantes);
        $this->assertContains('respaldo-demo-5.zip', $nombres);
        $this->assertContains('respaldo-demo-4.zip', $nombres);
    }

    public function test_rotar_con_keep_cero_no_borra_nada(): void
    {
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'respaldo-demo-1.zip', 'x');

        $this->assertSame(0, (new BackupService())->rotar($this->dir, 0));
        $this->assertCount(1, glob($this->dir.DIRECTORY_SEPARATOR.'respaldo-*.zip'));
    }
}
