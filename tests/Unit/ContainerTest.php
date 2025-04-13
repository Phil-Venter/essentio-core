<?php

use Essentio\Core\Container;

describe(Container::class, function () {
    it("binds and retrieves a service instance", function () {
        $container = new Container();
        $container->bind("stdClass", fn() => new stdClass());
        $instance = $container->resolve("stdClass");

        expect($instance)->toBeInstanceOf(stdClass::class);
    });

    it("throws an exception when retrieving an unbound service", function () {
        $container = new Container();

        expect(fn() => $container->resolve("nonexistent"))->toThrow(
            RuntimeException::class,
            "No binding for nonexistent exists"
        );
    });

    it("returns the same instance when the binding is marked as once (singleton)", function () {
        $container = new Container();
        $binding = $container->bind("singleton", fn() => new stdClass());
        $binding->once();

        $instance1 = $container->resolve("singleton");
        $instance2 = $container->resolve("singleton");

        expect($instance1)->toBe($instance2);
    });

    it("returns different instances when the binding is not marked as once (prototype)", function () {
        $container = new Container();
        $container->bind("prototype", fn() => new stdClass());

        $instance1 = $container->resolve("prototype");
        $instance2 = $container->resolve("prototype");

        expect($instance1)->not->toBe($instance2);
    });

    it("passes the container instance to the factory", function () {
        $container = new Container();
        $container->bind("self", function ($c) {
            return $c;
        });

        expect($container->resolve("self"))->toBe($container);
    });

    it("can bind and retrieve multiple services independently", function () {
        $container = new Container();
        $container->bind("serviceA", fn() => new stdClass());
        $container->bind(
            "serviceB",
            fn() => new class {
                public $value = "serviceB";
            }
        );

        $instanceA = $container->resolve("serviceA");
        $instanceB = $container->resolve("serviceB");

        expect($instanceA)->toBeInstanceOf(stdClass::class);
        expect($instanceB)->toBeInstanceOf(get_class($instanceB));
        expect(property_exists($instanceB, "value"))->toBeTrue();
        expect($instanceB->value)->toBe("serviceB");
    });
});
