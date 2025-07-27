<?php

use Essentio\Container;
use Essentio\FrameworkException;

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
    $instance = $container->get(DummyService::class);

    expect($instance)->toBeInstanceOf(DummyService::class);
});

it("binds using a custom factory", function () {
    $container = new Container();

    $container->bind("custom", fn() => new DummyService());
    $resolved = $container->get("custom");

    expect($resolved)->toBeInstanceOf(DummyService::class);
});

test("once() creates a singleton instance", function () {
    $container = new Container();

    $container->once(DummyService::class, fn() => new DummyService());
    $a = $container->get(DummyService::class);
    $b = $container->get(DummyService::class);

    expect($a)->toBe($b); // same instance
});

test("get() instantiates class directly if unbound", function () {
    $container = new Container();

    $instance = $container->get(DummyService::class);

    expect($instance)->toBeInstanceOf(DummyService::class);
});

test("get() passes constructor parameters", function () {
    $container = new Container();

    $instance = $container->get(ParamService::class, ["John"]);

    expect($instance)->toBeInstanceOf(ParamService::class)->and($instance->name)->toBe("John");
});

it("throws if class does not exist in bind", function () {
    $container = new Container();

    expect(fn() => $container->bind("test", "NonExistentClass"))
        ->toThrow(FrameworkException::class)
        ->and(fn($e) => expect($e->getMessage())->toContain("Cannot bind"));
});

it("throws if resolve fails for unknown non-class", function () {
    $container = new Container();

    expect(fn() => $container->get("not_bound_and_invalid_class"))
        ->toThrow(FrameworkException::class)
        ->and(fn($e) => expect($e->getMessage())->toContain("Service [not_bound_and_invalid_class] is not bound"));
});

test("get() returns new instance for bind each time", function () {
    $container = new Container();

    $container->bind(DummyService::class, fn() => new DummyService());
    $a = $container->get(DummyService::class);
    $b = $container->get(DummyService::class);

    expect($a)->not()->toBe($b);
});
