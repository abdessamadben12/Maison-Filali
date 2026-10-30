<?php

namespace Tests\Feature;

use App\Support\ResponsiveImages;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ResponsiveImagesTest extends TestCase
{
    public function test_images_are_resized_offline_and_originals_remain_usable(): void
    {
        $directory = storage_path('framework/testing/image-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory.'/images');
        $this->app->usePublicPath($directory);
        try {
            $image = imagecreatetruecolor(600, 900);
            imagefill($image, 0, 0, imagecolorallocate($image, 140, 30, 50));
            imagepng($image, $directory.'/images/test.png');
            imagedestroy($image);
            $originalHash = hash_file('sha256', $directory.'/images/test.png');
            $url = asset('images/test.png');
            $this->assertSame(['src' => $url], (new ResponsiveImages)->attributes($url));

            $this->artisan('shop:optimize-images')->assertSuccessful();
            $attributes = (new ResponsiveImages)->attributes($url);
            $this->assertStringContainsString('.webp', $attributes['src']);
            $this->assertStringContainsString('128w', $attributes['srcset']);
            $this->assertStringContainsString('480w', $attributes['srcset']);
            $this->assertSame(600, $attributes['width']);
            $this->assertSame(900, $attributes['height']);
            $this->assertSame($originalHash, hash_file('sha256', $directory.'/images/test.png'));
            $manifest = json_decode(file_get_contents($directory.'/images/optimized/manifest.json'), true);
            foreach ($manifest['images/test.png']['variants'] as $width => $path) {
                $size = getimagesize($directory.'/'.$path);
                $this->assertSame((int) $width, $size[0]);
                $this->assertSame((int) ($width * 1.5), $size[1]);
                $this->assertSame('image/webp', $size['mime']);
            }

            $this->artisan('shop:optimize-images')->assertSuccessful();
            $this->assertSame($attributes, (new ResponsiveImages)->attributes($url));
            $external = 'https://example.com/photo.jpg';
            $this->assertSame(['src' => $external], (new ResponsiveImages)->attributes($external));
            touch($directory.'/images/test.png', time() + 10);
            clearstatcache();
            $this->assertSame(['src' => $url], (new ResponsiveImages)->attributes($url));
        } finally {
            // Only this test's randomly named directory inside the testing root.
            $root = realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR;
            if (str_starts_with(realpath($directory), $root)) {
                File::deleteDirectory($directory);
            }
        }
    }
}
