<?php

namespace Essentio\Core\Experimental;

use ReflectionException;
use ArgumentCountError;
use TypeError;
use Error;
use Throwable;
use BadMethodCallException;
use LogicException;
use ReflectionMethod;

use Essentio\Core\Container;

/**
 * @api
 */
abstract class StaticProxy
{
    /** @var array<string,ReflectionMethod> */
    protected static array $reflections = [];

    /**
     * Returns the fully-qualified class name that this proxy delegates to.
     *
     * @return class-string The name of the target class
     */
    abstract protected static function delegatesTo(): string;

    /**
     * Dynamically calls a static or instance method on the delegated class.
     *
     * @param string       $name      Method name to invoke
     * @param list<mixed>  $arguments Arguments to pass to the method
     *
     * @return mixed                  Response from the underlying method call
     *
     * @throws LogicException If the target class does not exist
     * @throws BadMethodCallException If the method does not exist or is not public
     * @throws ReflectionException If method reflection fails unexpectedly
     * @throws ArgumentCountError If incorrect number of arguments is provided
     * @throws TypeError If argument types do not match the method signature
     * @throws Error If invocation or instantiation fails for other reasons
     * @throws Throwable Exceptions from target constructors or methods may propagate through this proxy.
     */
    final public static function __callStatic(string $name, array $arguments): mixed
    {
        $target = static::delegatesTo();
        assert(class_exists($target, true), new LogicException(sprintf('Target class %s does not exist.', $target)));
        assert(method_exists($target, $name), new BadMethodCallException(sprintf('Method %s does not exist on class %s.', $name, $target)));
        static::$reflections[($key = sprintf('%s::%s', $target, $name))] ??= new ReflectionMethod($target, $name);
        assert(static::$reflections[$key]->isPublic(), new BadMethodCallException(sprintf('Method %s is not public on class %s.', $name, $target)));
        return static::$reflections[$key]->isStatic() ? $target::$name(...$arguments) : Container::instance()->get($target)->$name(...$arguments);
    }
}
