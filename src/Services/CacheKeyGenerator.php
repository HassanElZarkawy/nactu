<?php

declare(strict_types=1);

namespace Nactu\Services;

use ReflectionClass;
use ReflectionProperty;

/**
 * Generates deterministic, collision-resistant cache keys from objects
 */
class CacheKeyGenerator
{
    private const string ARRAY_PREFIX = 'arr';

    private const string NULL_VALUE = 'null';

    private const string TRUE_VALUE = 'true';

    private const string FALSE_VALUE = 'false';

    /**
     * Maximum nesting depth when hashing nested objects.
     * Objects beyond this depth are represented by a sentinel to guard
     * against infinite recursion on cyclic references.
     */
    private const int MAX_DEPTH = 5;

    private const string MAX_DEPTH_VALUE = 'max_depth_reached';

    private string $keySeparator;

    /** @var array<class-string, string> */
    private static array $classNameCache = [];

    /**
     * Cached per class: null means every instance property is public and the
     * get_object_vars() fast path applies, otherwise it is the reflection
     * property list used for classes with non-public properties.
     *
     * @var array<class-string, list<ReflectionProperty>|null>
     */
    private static array $propertyCache = [];

    public function __construct(string $keySeparator = ':')
    {
        $this->keySeparator = $keySeparator;
    }

    /**
     * Generate a cache key from a command object
     */
    public function generateFromCommand(object $command, ?string $prefix = null): string
    {
        $parts = [];

        if ($prefix !== null && $prefix !== '') {
            $parts[] = $prefix;
        }

        $parts[] = $this->getNormalizedClassName($command::class);
        $parts[] = $this->hashProperties($this->extractProperties($command));

        return implode($this->keySeparator, $parts);
    }

    /**
     * Generate a cache key with custom parts
     *
     * @param  array<mixed>  $parts
     */
    public function generate(array $parts): string
    {
        $normalizedParts = array_map(
            $this->normalizePart(...),
            array_filter($parts, fn (mixed $part): bool => $part !== null && $part !== ''),
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
     *
     * @param  array<string, mixed>  $filters
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
     * Extract properties from an object with a per-class strategy cache.
     *
     * Public-only classes (the generated DTO shape) use a single native
     * get_object_vars() call — no reflection per dispatch. Classes with
     * non-public properties keep the reflection path. Both emit properties in
     * declaration order, which is deterministic per class, so no sorting is
     * needed for a stable key.
     *
     * @return array<string, mixed>
     */
    private function extractProperties(object $object): array
    {
        $class = $object::class;

        // The cached value can legitimately be null (the get_object_vars()
        // fast-path marker), so ??= cannot be used here — it would re-resolve
        // the strategy on every call for fast-path classes.
        if (! array_key_exists($class, self::$propertyCache)) {
            self::$propertyCache[$class] = $this->resolvePropertyStrategy($class);
        }

        $reflection = self::$propertyCache[$class];

        if ($reflection === null) {
            // Fast path: get_object_vars() returns public properties in
            // declaration order and omits uninitialized typed properties.
            $properties = [];

            foreach (get_object_vars($object) as $name => $value) {
                if ($value !== null) {
                    $properties[$name] = $value;
                }
            }

            return $properties;
        }

        $properties = [];

        foreach ($reflection as $property) {
            // Uninitialized typed properties would throw on getValue()
            if (! $property->isInitialized($object)) {
                continue;
            }

            $value = $property->getValue($object);

            if ($value !== null) {
                $properties[$property->getName()] = $value;
            }
        }

        return $properties;
    }

    /**
     * Pick the extraction strategy for a class, once.
     *
     * @param  class-string  $class
     * @return list<ReflectionProperty>|null null = use the get_object_vars() fast path
     */
    private function resolvePropertyStrategy(string $class): ?array
    {
        $properties = (new ReflectionClass($class))->getProperties();

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (! $property->isPublic()) {
                return array_values(array_filter(
                    $properties,
                    fn (ReflectionProperty $property): bool => ! $property->isStatic(),
                ));
            }
        }

        return null;
    }

    /**
     * Hash properties into a short, deterministic string
     * Uses XXH128: a 128-bit hash that is fast enough for cache keys while
     * making collisions practically impossible (unlike the previous CRC32)
     *
     * @param  array<string, mixed>  $properties
     */
    private function hashProperties(array $properties, int $depth = 0): string
    {
        if ($properties === []) {
            return 'default';
        }

        return hash('xxh128', $this->serializeProperties($properties, $depth));
    }

    /**
     * Serialize properties in a deterministic way
     *
     * @param  array<string, mixed>  $properties
     */
    private function serializeProperties(array $properties, int $depth): string
    {
        $serialized = '';

        foreach ($properties as $key => $value) {
            $serialized .= $key.'='.$this->serializeValue($value, $depth).'&';
        }

        return $serialized === '' ? '' : substr($serialized, 0, -1);
    }

    /**
     * Serialize a single value
     */
    private function serializeValue(mixed $value, int $depth = 0): string
    {
        return match (true) {
            is_null($value) => self::NULL_VALUE,
            is_bool($value) => $value ? self::TRUE_VALUE : self::FALSE_VALUE,
            is_array($value) => $this->serializeArray($value),
            is_object($value) => $this->serializeObject($value, $depth),
            default => (string) $value,
        };
    }

    /**
     * Serialize an array value. Falls back to serialize() when the value is
     * not JSON-encodable (e.g. contains invalid UTF-8) so the key stays deterministic
     *
     * @param  array<mixed>  $value
     */
    private function serializeArray(array $value): string
    {
        $encoded = json_encode($value);

        if ($encoded === false) {
            $encoded = serialize($value);
        }

        return self::ARRAY_PREFIX.hash('xxh128', $encoded);
    }

    /**
     * Serialize an object value by hashing its properties, with a depth
     * guard for cyclic references
     */
    private function serializeObject(object $value, int $depth): string
    {
        if ($depth >= self::MAX_DEPTH) {
            return self::MAX_DEPTH_VALUE;
        }

        return $this->hashProperties($this->extractProperties($value), $depth + 1);
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
     * Get the normalized fully-qualified class name with caching to avoid repeated reflection
     *
     * @param  class-string  $class
     */
    private function getNormalizedClassName(string $class): string
    {
        return self::$classNameCache[$class] ??= $this->normalizeClassName($class);
    }

    /**
     * Normalize a fully-qualified class name for use in cache keys.
     * The full namespace is included so that two classes sharing a short
     * name can never collide
     */
    private function normalizeClassName(string $className): string
    {
        // Convert PascalCase to snake_case
        $snakeCase = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        // Remove common suffixes
        $snakeCase = str_replace(['_command', '_query'], '', $snakeCase);

        // Namespace separators are not key-friendly
        return strtr($snakeCase, '\\', '_');
    }
}
