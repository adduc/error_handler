<?php

namespace Adduc\ErrorHandler;

/**
 * This class aims to allow for defining handlers for errors, exceptions, and
 * shutdown events, allowing for dynamic registration and unregistration of
 * handlers. This class handles registering shutdown functions different from
 * PHP, using the newest registered handler rather than the old one.
 */
abstract class ErrorHandler {

    protected static
        $registered = false,
        $stack = array(
            'error' => array(),
            'exception' => array(),
            'shutdown' => array()
        );

    /**
     * Register a callable as a handler for the given type.
     *
     * @param string $type ['error', 'exception', 'shutdown']
     * @param callable $callable
     * @return void
     */
    public static function register($type, $callable) {

        if(!isset(static::$stack[$type])) {
            throw new \Exception("Unrecognized Type.");
        }

        if(!is_callable($callable)) {
            throw new \Exception("Cannot registered a non-callable variable.");
        }

        array_unshift(static::$stack[$type], func_get_args());

        if(!static::$registered) {
            static::$registered = true;
            register_shutdown_function(array(__CLASS__, 'shutdown'));
            set_error_handler(array(__CLASS__, 'error'));
            set_exception_handler(array(__CLASS__, 'exception'));
        }
    }

    /**
     * Unregister a callable as a handler for the given type.
     *
     * @param string $type ['error', 'exception', 'shutdown']
     * @param callable $callable
     * @return void
     */
    public static function unregister($type, $callable) {
        if(!isset(static::$stack[$type])) {
            throw new \Exception("Unrecognized Type.");
        }

        if($key = array_search($callable, static::$stack[$type])) {
            unset(static::$stack[$type]);
        }
    }

    /**
     * Process an exception, calling the latest registered handler.
     *
     * @return void
     */
    public static function exception(\Exception $e) {
        $func = current(static::$stack['exception']);
        $func && $func[1]($e);
    }

    /**
     * Process an error, calling each registered handler until a non-false
     * response is received.
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return void
     */
    public static function error($errno, $errstr, $errfile, $errline) {
        foreach(static::$stack['error'] as $args) {
            if($args[1]($errno, $errstr, $errfile, $errline) !== false) {
                break;
            }
        }
    }

    /**
     * Process the shutdown event, calling each handler (from newly registered
     * to oldest)
     *
     * @return void
     */
    public static function shutdown() {
        foreach(static::$stack['shutdown'] as $args) {
            call_user_func_array($args[1], array_slice($args, 2));
        }
    }
}
