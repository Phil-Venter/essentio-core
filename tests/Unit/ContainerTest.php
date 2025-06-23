<?php

use Essentio\Core\Container;

class DummyService
{
    public string $value = "default";
}

class ParamService
{
    public function __construct(public string $name) {}
}

it("binds and resolves a class by default name", function () {
    $container = new Container();

    $container->bind(DummyService::class);
    $instance = $container->resolve(DummyService::class);

    expect($instance)->toBeInstanceOf(DummyService::class);
});

it("binds using a custom factory", function () {
    $container = new Container();

    $container->bind("custom", fn() => new DummyService());
    $resolved = $container->resolve("custom");

    expect($resolved)->toBeInstanceOf(DummyService::class);
});

test("once() creates a singleton instance", function () {
    $container = new Container();

    $container->once(DummyService::class, fn() => new DummyService());
    $a = $container->resolve(DummyService::class);
    $b = $container->resolve(DummyService::class);

    expect($a)->toBe($b); // same instance
});

test("resolve() instantiates class directly if unbound", function () {
    $container = new Container();

    $instance = $container->resolve(DummyService::class);

    expect($instance)->toBeInstanceOf(DummyService::class);
});

test("resolve() passes constructor parameters", function () {
    $container = new Container();

    $instance = $container->resolve(ParamService::class, ["John"]);

    expect($instance)->toBeInstanceOf(ParamService::class)->and($instance->name)->toBe("John");
});

it("throws if class does not exist in bind", function () {
    $container = new Container();

    expect(fn() => $container->bind("test", "NonExistentClass"))
        ->toThrow(RuntimeException::class)
        ->and(fn($e) => expect($e->getMessage())->toContain("Cannot bind"));
});

it("throws if resolve fails for unknown non-class", function () {
    $container = new Container();

    expect(fn() => $container->resolve("not_bound_and_invalid_class"))
        ->toThrow(RuntimeException::class)
        ->and(fn($e) => expect($e->getMessage())->toContain("Service [not_bound_and_invalid_class] is not bound"));
});

test("resolve() returns new instance for bind each time", function () {
    $container = new Container();

    $container->bind(DummyService::class, fn() => new DummyService());
    $a = $container->resolve(DummyService::class);
    $b = $container->resolve(DummyService::class);

    expect($a)->not()->toBe($b);
});
