<?php

namespace App\Services;

use App\Models\Contractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImageProcessor
{
    /**
     * @return array{path:string,url:string,size_bytes:int,width:int,height:int}
     */
    public function processAndStore(UploadedFile $file, Contractor $contractor, string $directory = 'products/gallery'): array
    {
        $raw = $file->getContent();
        if ($raw === false) {
            throw new RuntimeException('Falha ao ler o arquivo de imagem enviado.');
        }

        $image = @imagecreatefromstring($raw);
        if (! $image) {
            throw new RuntimeException('Formato de imagem inválido ou não suportado.');
        }

        $originalWidth = (int) imagesx($image);
        $originalHeight = (int) imagesy($image);

        if ($originalWidth <= 0 || $originalHeight <= 0) {
            imagedestroy($image);
            throw new RuntimeException('Dimensão de imagem inválida.');
        }

        [$targetWidth, $targetHeight] = $this->fitWithin($originalWidth, $originalHeight, 1600, 1600);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas) {
            imagedestroy($image);
            throw new RuntimeException('Não foi possível preparar o processamento da imagem.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $originalWidth,
            $originalHeight
        );

        ob_start();
        $encoded = imagewebp($canvas, null, 82);
        $binary = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($image);

        if (! $encoded || $binary === '') {
            throw new RuntimeException('Não foi possível gerar a imagem otimizada.');
        }

        $filename = Str::lower(Str::uuid()->toString()).'.webp';
        $path = "contractors/{$contractor->id}/{$directory}/{$filename}";

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'size_bytes' => strlen($binary),
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function fitWithin(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return [$width, $height];
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) floor($width * $ratio));
        $targetHeight = max(1, (int) floor($height * $ratio));

        return [$targetWidth, $targetHeight];
    }
}

