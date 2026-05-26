<?php

namespace Nactu\Services;

use ReflectionClass;
use ReflectionException;

/**
 * Generates deterministic, collision-resistant cache keys from objects
 */
class CacheKeyGenerator
{
    private const string ARRAY_PREFIX = 'arr';

    private const string NULL_VALUE = 'null';

    private const string TRUE_VALUE = 'true';

    private const string FALSE_VALUE = 'false';

    private string $keySeparator;

    private array $classNameCache = [];

    private array $propertyCache = [];

    public function __construct(string $keySeparator = ':')
    {
        $this->keySeparator = $keySeparator;
    }

    /**
     * Generate a cache key from a command object
     */
    public function generateFromCommand(object $command, ?string $prefix = null): string
    {
        $className = $this->getCachedClassName($command);
        $properties = $this->extractProperties($command);

        $keyParts = array_filter([
            $prefix,
            $this->normalizeClassName($className),
            $this->hashProperties($properties),
        ]);

        return implode($this->keySeparator, $keyParts);
    }

    /**
     * Generate a cache key with custom parts
     */
    public function generate(array $parts): string
    {
        $normalizedParts = array_map(
            $this->normalizePart(...),
            array_filter($parts)
        );

        return implode($this->keySeparator, $normalizedParts);
    }

    /**
     * Generate cache key for a specific entity
     */
    public function forEntity(string $entityType, int|string $id, ?string $suffix = null): string
    {
        $parts = [$entityType, $id];

        if ($suffix !== null) {
            $parts[] = $suffix;
        }

        return $this->generate($parts);
    }

    /**
     * Generate cache key for a list query
     */
    public function forList(string $entityType, array $filters = [], ?int $page = null): string
    {
        $parts = [$entityType, 'list'];

        if ($filters !== []) {
            $parts[] = $this->hashProperties($filters);
        }

        if ($page !== null) {
            $parts[] = "page:$page";
        }

        return $this->generate($parts);
    }

    /**
     * Extract properties from an object using reflection with per-class caching
     * Reflection is done once per class, subsequent calls reuse cached property list
     * @throws ReflectionException
     */
    private function extractProperties(object $object): array
    {
        $class = $object::class;

        if (! isset($this->propertyCache[$class])) {
            $this->propertyCache[$class] = new ReflectionClass($class)->getProperties();
        }

        $properties = [];

        foreach ($this->propertyCache[$class] as $property) {
            $value = $property->getValue($object);

            if ($value !== null) {
                $properties[$property->getName()] = $value;
            }
        }

        ksort($properties);

        return $properties;
    }

    /**
     * Hash properties into a short, deterministic string
     * Uses CRC32 for speed (10-20x faster than MD5)
     */
    private function hashProperties(array $properties): string
    {
        if ($properties === []) {
            return 'default';
        }

        $serialized = $this->serializeProperties($properties);

        // CRC32 is faster than MD5 and sufficient for cache key uniqueness
        return sprintf('%x', crc32($serialized));
    }

    /**
     * Serialize properties in a deterministic way
     */
    private function serializeProperties(array $properties): string
    {
        $parts = [];

        foreach ($properties as $key => $value) {
            $parts[] = $key.'='.$this->serializeValue($value);
        }

        return implode('&', $parts);
    }

    /**
     * Serialize a single value
     */
    private function serializeValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => self::NULL_VALUE,
            is_bool($value) => $value ? self::TRUE_VALUE : self::FALSE_VALUE,
            is_array($value) => self::ARRAY_PREFIX.sprintf('%x', crc32(json_encode($value))),
            is_object($value) => $this->hashProperties($this->extractProperties($value)),
            default => (string) $value,
        };
    }

    /**
     * Normalize a part for use in cache key
     */
    private function normalizePart(mixed $part): string
    {
        if (is_array($part)) {
            return $this->hashProperties($part);
        }

        if (is_object($part)) {
            return $this->hashProperties($this->extractProperties($part));
        }

        // Convert to string and sanitize using strtr (faster than preg_replace)
        $string = (string) $part;

        return strtr($string, ' \\/!"#$%&\'()*+,.:;<=>?@[`{|}~', '____________________________');
    }

    /**
     * Get short class name with caching to avoid repeated reflection
     */
    private function getCachedClassName(object $object): string
    {
        $class = $object::class;

        if (! isset($this->classNameCache[$class])) {
            // Extract short name once and cache it
            $parts = explode('\\', $class);
            $this->classNameCache[$class] = end($parts);
        }

        return $this->classNameCache[$class];
    }

    /**
     * Normalize class name for cache key
     */
    private function normalizeClassName(string $className): string
    {
        // Convert PascalCase to snake_case
        $snakeCase = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        // Remove common suffixes
        return str_replace(['_command', '_query'], '', $snakeCase);
    }
}
