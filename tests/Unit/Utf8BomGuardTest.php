<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Utf8BomGuardTest extends TestCase
{
    #[Test]
    public function php_source_files_do_not_start_with_utf8_bom(): void
    {
        $paths = [
            base_path('app'),
            base_path('bootstrap'),
            base_path('config'),
            base_path('database'),
            base_path('lang'),
            base_path('routes'),
            base_path('tests'),
        ];

        $filesWithBom = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $handle = @fopen($file->getPathname(), 'rb');
                if ($handle === false) {
                    continue;
                }

                $prefix = fread($handle, 3);
                fclose($handle);

                if ($prefix === "\xEF\xBB\xBF") {
                    $filesWithBom[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $filesWithBom, 'Arquivos PHP com BOM encontrados: '.implode(', ', $filesWithBom));
    }
}
