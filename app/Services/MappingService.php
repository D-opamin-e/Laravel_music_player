<?php

namespace App\Services;

class MappingService
{
    protected $mappings = [];

    public function __construct()
    {
        $this->loadMappings();
    }

    protected function loadMappings()
    {
        $filePath = storage_path('app/Mapping.txt'); 

        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, '->') !== false) {
                [$key, $value] = array_map('trim', explode('->', $line, 2));
                $this->mappings[$key] = $value;
            }
        }
    }

    public function map(string $query): ?string
    {
        $query = trim($query);
        $normalized = $this->normalizeQuery($query);
    
        \Log::info("Raw: [$query], Normalized: [$normalized]");
    
        if (isset($this->mappings[$normalized])) {
            \Log::info("Mapped (exact): " . $this->mappings[$normalized]);
            return $this->mappings[$normalized];
        }
    
        foreach ($this->mappings as $key => $value) {
            if (stripos($normalized, $key) !== false) {
                \Log::info("Mapped (contains): " . $value);
                return $value;
            }
        }
    
        \Log::info("No match found for: " . $query);
        return null;
    }
    
    

    public function reverseMap(string $query): ?string
    {
        foreach ($this->mappings as $key => $value) {
            if (mb_strtolower($value) === mb_strtolower($query)) {
                return $key;
            }
        }

        return null;
    }
    public function getAliasesForValue(string $query): array
    {
        $aliases = [];

        foreach ($this->mappings as $key => $value) {
            if (mb_strtolower($value) === mb_strtolower($query)) {
                $aliases[] = $key;
            }
        }

        return $aliases;
    }

    public function normalizeQuery(string $query): string
    {
        $query = preg_replace('/\s*-\s*Topic$/i', '', $query);
        return trim($query);
    }

    public function getMappedValue(string $query): ?string
    {
        return $this->map($query); // 수정된 map() 사용
    }
}
