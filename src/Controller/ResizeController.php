<?php
namespace App\Controller;

class ResizeController
{
    protected $image;
    protected $image_type;

    public function load(string $filename): void
    {
        $image_info = @getimagesize($filename);
        if ($image_info === false) {
            throw new \RuntimeException("Unable to get image size for file: $filename");
        }
        $this->image_type = $image_info[2];
        switch ($this->image_type) {
            case IMAGETYPE_JPEG:
                $this->image = @imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_GIF:
                $this->image = @imagecreatefromgif($filename);
                break;
            case IMAGETYPE_PNG:
                $this->image = @imagecreatefrompng($filename);
                break;
            default:
                throw new \RuntimeException("Unsupported image type: {$this->image_type}");
        }
        if ($this->image === false || $this->image === null) {
            throw new \RuntimeException("Failed to create image resource from file: $filename");
        }
    }

    public function save(string $filename, int $image_type = null, int $jpegQuality = 75, $permissions = null): void
    {
        $image_type = $image_type ?? $this->image_type;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                imagejpeg($this->image, $filename, $jpegQuality);
                break;
            case IMAGETYPE_PNG:
                // map jpeg-like quality (0-100) to png compression level (0-9)
                $pngLevel = (int) round((100 - max(0, min(100, $jpegQuality))) / 11.111);
                imagepng($this->image, $filename, $pngLevel);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->image, $filename);
                break;
            default:
                throw new \RuntimeException("Unsupported image type: {$image_type}");
        }

        if ($permissions !== null) {
            @chmod($filename, $permissions);
        }
    }

    public function output(int $image_type = null): void
    {
        $image_type = $image_type ?? $this->image_type;
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                imagejpeg($this->image);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->image);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->image);
                break;
            default:
                throw new \RuntimeException("Unsupported image type: {$image_type}");
        }
    }

    public function getWidth(): int
    {
        return imagesx($this->image);
    }

    public function getHeight(): int
    {
        return imagesy($this->image);
    }

    public function resizeToHeight(int $height): void
    {
        if ($height <= 0) {
            throw new \InvalidArgumentException('Height must be > 0');
        }
        $ratio = $height / $this->getHeight();
        $width = (int) round($this->getWidth() * $ratio);
        $this->resize($width, $height);
    }

    public function resizeToWidth(int $width): void
    {
        if ($width <= 0) {
            throw new \InvalidArgumentException('Width must be > 0');
        }
        $ratio = $width / $this->getWidth();
        $height = (int) round($this->getHeight() * $ratio);
        $this->resize($width, $height);
    }

    public function scale(float $scale): void
    {
        if ($scale <= 0) {
            throw new \InvalidArgumentException('Scale must be > 0');
        }
        $width = (int) round($this->getWidth() * $scale / 100);
        $height = (int) round($this->getHeight() * $scale / 100);
        $this->resize($width, $height);
    }

    public function resize(int $width, int $height): void
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Width/Height must be > 0');
        }

        $new_image = imagecreatetruecolor($width, $height);
        if (in_array($this->image_type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $width, $height, $transparent);
        }

        imagecopyresampled(
            $new_image,
            $this->image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $this->getWidth(),
            $this->getHeight()
        );
        $this->image = $new_image;
    }
}
