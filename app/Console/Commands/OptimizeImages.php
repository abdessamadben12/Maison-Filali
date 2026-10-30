<?php

namespace App\Console\Commands;

use App\Support\Img;
use Illuminate\Console\Command;

class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--force : Recréer toutes les versions WebP}';

    protected $description = 'Crée les versions WebP légères des photos produits et catégories (public/uploads)';

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('L’extension PHP « gd » avec WebP est nécessaire (php.ini : extension=gd).');

            return self::FAILURE;
        }
        $files = collect(glob(public_path('uploads/{products,categories}/*.{jpg,jpeg,png,JPG,JPEG,PNG}'), GLOB_BRACE));
        $before = $after = 0;
        foreach ($files as $file) {
            $rel = ltrim(str_replace(public_path('uploads'), '', $file), '/\\');
            $rel = str_replace('\\', '/', $rel);
            $before += filesize($file);
            $after += Img::optimize($rel, (bool) $this->option('force')) ? filesize(public_path('uploads/'.Img::webpPath($rel))) : filesize($file);
        }
        $this->info($files->count().' photos · '.round($before / 1024).' Ko → '.round($after / 1024).' Ko');

        return self::SUCCESS;
    }
}
