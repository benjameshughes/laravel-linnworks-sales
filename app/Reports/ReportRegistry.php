<?php

namespace App\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class ReportRegistry
{
    private static ?Collection $reports = null;

    public static function all(): Collection
    {
        if (self::$reports !== null) {
            return self::$reports;
        }

        self::$reports = collect();

        $reportFiles = File::allFiles(app_path('Reports'));

        foreach ($reportFiles as $file) {
            $relativePath = str_replace(app_path('Reports').'/', '', $file->getPathname());
            $relativePath = str_replace('.php', '', $relativePath);
            $relativePath = str_replace('/', '\\', $relativePath);

            $className = 'App\\Reports\\'.$relativePath;

            if (class_exists($className) &&
                is_subclass_of($className, AbstractReport::class) &&
                ! (new ReflectionClass($className))->isAbstract()) {
                self::$reports->push(new $className);
            }
        }

        return self::$reports;
    }

    public static function byCategory(): Collection
    {
        return self::all()->groupBy(fn ($r) => $r->category()->value);
    }

    public static function find(string $className): ?AbstractReport
    {
        return self::all()->first(fn ($r) => get_class($r) === $className);
    }

    public static function findBySlug(string $slug): ?AbstractReport
    {
        return self::all()->first(fn ($r) => $r->slug() === $slug);
    }

    public static function refresh(): void
    {
        self::$reports = null;
    }
}
