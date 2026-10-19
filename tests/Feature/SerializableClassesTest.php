<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\CacheManager;
use Tests\TestCase;
use Tests\TestClass\SerializableTestChildClass;
use Tests\TestClass\SerializableTestClass;
use Tests\TestClass\StringableTestClassPhp8;

class SerializableClassesTest extends TestCase
{
    private const STORES = ['redis', 'memcached', 'file', 'hierarchical_file'];

    private function getCache(): CacheManager
    {
        return $this->app['cache'];
    }

    /**
     * Rebuilds the store so it reads the current `cache.serializable_classes` config value.
     *
     * @param array|bool|null $serializableClasses
     */
    private function makeStore(string $name, $serializableClasses, array $baseClasses = [])
    {
        $this->app['config']->set('cache.serializable_classes', $serializableClasses);
        $this->app['config']->set('cache.serializable_base_classes', $baseClasses);
        $this->getCache()->forgetDriver($name);

        return $this->getCache()->store($name);
    }

    private function skipIfUnavailable(string $name): void
    {
        if ($name === 'memcached' && !class_exists('\Memcached')) {
            static::markTestSkipped('Memcached extension is not installed.');
        }
    }

    protected function tearDown(): void
    {
        foreach (self::STORES as $name) {
            if ($name === 'memcached' && !class_exists('\Memcached')) {
                continue;
            }
            $this->makeStore($name, null)->flush();
        }
        parent::tearDown();
    }

    /**
     * @dataProvider unrestrictedProvider
     * @param array|bool|null $serializableClasses
     */
    public function testObjectIsRestoredWhenAllowed(string $name, $serializableClasses): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, $serializableClasses);
        $store->put('object', new SerializableTestClass('abc'), 3600);

        $value = $store->get('object');
        static::assertInstanceOf(SerializableTestClass::class, $value);
        static::assertSame('abc', $value->value);
    }

    public static function unrestrictedProvider(): array
    {
        $cases = [];
        foreach (self::STORES as $name) {
            $cases["$name: null (no restrictions)"] = [$name, null];
            $cases["$name: true"] = [$name, true];
            $cases["$name: allow-list with class"] = [$name, [SerializableTestClass::class]];
        }
        return $cases;
    }

    /**
     * @dataProvider restrictedProvider
     * @param array|bool $serializableClasses
     */
    public function testObjectIsNotRestoredWhenDisallowed(string $name, $serializableClasses): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, $serializableClasses);
        $store->put('object', new SerializableTestClass('abc'), 3600);

        $value = $store->get('object');
        static::assertNotInstanceOf(SerializableTestClass::class, $value);
        static::assertInstanceOf(\__PHP_Incomplete_Class::class, $value);
    }

    public static function restrictedProvider(): array
    {
        $cases = [];
        foreach (self::STORES as $name) {
            $cases["$name: false"] = [$name, false];
            $cases["$name: empty allow-list"] = [$name, []];
            $cases["$name: allow-list without class"] = [$name, [\stdClass::class]];
        }
        return $cases;
    }

    /**
     * @dataProvider storesProvider
     */
    public function testScalarsAndArraysAreNotAffectedByRestrictions(string $name): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, false);
        $store->put('array', ['a' => 1, 'b' => [true, null, 'x']], 3600);
        $store->put('string', 'text', 3600);

        static::assertSame(['a' => 1, 'b' => [true, null, 'x']], $store->get('array'));
        static::assertSame('text', $store->get('string'));
    }

    public static function storesProvider(): array
    {
        return array_map(static fn(string $name) => [$name], array_combine(self::STORES, self::STORES));
    }

    /**
     * @dataProvider storesProvider
     */
    public function testRestrictionAppliesToTaggedCache(string $name): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, false);
        $store->tags(['tag1'])->put('object', new SerializableTestClass('abc'), 3600);

        static::assertInstanceOf(\__PHP_Incomplete_Class::class, $store->tags(['tag1'])->get('object'));
    }

    /**
     * @dataProvider storesProvider
     */
    public function testStoresBuiltWithoutExplicitConfigAreUnrestricted(string $name): void
    {
        $this->skipIfUnavailable($name);
        $this->app['config']->offsetUnset('cache.serializable_classes');
        $this->getCache()->forgetDriver($name);
        $store = $this->getCache()->store($name);
        $store->put('object', new SerializableTestClass('abc'), 3600);

        static::assertInstanceOf(SerializableTestClass::class, $store->get('object'));
    }

    /**
     * @dataProvider storesProvider
     */
    public function testSubclassesOfBaseClassAreAllowed(string $name): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, false, [SerializableTestClass::class]);
        $store->put('base', new SerializableTestClass('a'), 3600);
        $store->put('child', new SerializableTestChildClass('b'), 3600);

        static::assertInstanceOf(SerializableTestClass::class, $store->get('base'));
        $child = $store->get('child');
        static::assertInstanceOf(SerializableTestChildClass::class, $child);
        static::assertSame('b', $child->value);
    }

    /**
     * @dataProvider storesProvider
     */
    public function testClassesOutsideBaseClassStayBlocked(string $name): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, false, [SerializableTestChildClass::class]);
        $store->put('parent', new SerializableTestClass('a'), 3600);
        $store->put('other', new StringableTestClassPhp8('x'), 3600);

        static::assertInstanceOf(\__PHP_Incomplete_Class::class, $store->get('parent'));
        static::assertInstanceOf(\__PHP_Incomplete_Class::class, $store->get('other'));
    }

    /**
     * @dataProvider storesProvider
     */
    public function testBaseClassesWorkWithNestedObjectsAndInterfaces(string $name): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, [\stdClass::class], [SerializableTestClass::class, \Stringable::class]);
        $holder = new \stdClass();
        $holder->child = new SerializableTestChildClass('b');
        $holder->text = new StringableTestClassPhp8('s');
        $store->put('nested', $holder, 3600);

        $value = $store->get('nested');
        static::assertInstanceOf(\stdClass::class, $value);
        static::assertInstanceOf(SerializableTestChildClass::class, $value->child);
        static::assertInstanceOf(StringableTestClassPhp8::class, $value->text);
    }

    /**
     * @dataProvider storesProvider
     */
    public function testBaseClassesAreIgnoredWithoutRestrictions(string $name): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, null, [SerializableTestChildClass::class]);
        $store->put('other', new StringableTestClassPhp8('x'), 3600);

        static::assertInstanceOf(StringableTestClassPhp8::class, $store->get('other'));
    }

    /**
     * @dataProvider storesProvider
     */
    public function testStringContentLookingLikeObjectDoesNotAllowClasses(string $name): void
    {
        $this->skipIfUnavailable($name);
        $store = $this->makeStore($name, false, [SerializableTestChildClass::class]);
        $fake = 'O:' . strlen(StringableTestClassPhp8::class) . ':"' . StringableTestClassPhp8::class . '":0:{}';
        $store->put('mixed', ['text' => $fake, 'obj' => new StringableTestClassPhp8('x')], 3600);

        $value = $store->get('mixed');
        static::assertSame($fake, $value['text']);
        static::assertInstanceOf(\__PHP_Incomplete_Class::class, $value['obj']);
    }
}
