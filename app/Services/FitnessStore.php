<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FitnessStore
{
    private string $directory;

    public function __construct()
    {
        $this->directory = storage_path('app/fitness');
        File::ensureDirectoryExists($this->directory);
    }

    public function read(string $name, array $fallback = []): array
    {
        $path = $this->path($name);
        if (! File::exists($path)) return $fallback;
        try {
            $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function write(string $name, array $data): void
    {
        $path = $this->path($name);
        $temporary = $path.'.'.Str::uuid().'.tmp';
        File::put($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), true);
        File::move($temporary, $path);
    }

    private function path(string $name): string
    {
        abort_unless(in_array($name, ['profile', 'schedule', 'logs', 'weights'], true), 404);
        return $this->directory.'/'.$name.'.json';
    }
}
