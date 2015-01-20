<?php

namespace malkusch\phpmock;

/**
 * Mocking framework for built-in PHP functions.
 *
 * Mocking a build-in PHP function is achieved by using
 * PHP's namespace fallback policy. A mock will provide the namespaced function.
 * I.e. only unqualified functions in a non-global namespace can be mocked.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 * @see MockBuilder
 */
class Mock
{
    
    /**
     * @var string namespace for the mock function.
     */
    private $namespace;
    
    /**
     * @var string function name of the mocked function.
     */
    private $name;
    
    /**
     * @var callable The function mock.
     */
    private $function;
    
    /**
     * @var Recorder Call recorder.
     */
    private $recorder;
    
    /**
     * Set the namespace, function name and the mock function.
     *
     * @param string   $namespace  The namespace for the mock function.
     * @param string   $name       The function name of the mocked function.
     * @param callable $function   The mock function.
     */
    public function __construct($namespace, $name, callable $function)
    {
        $this->namespace = $namespace;
        $this->name = $name;
        $this->function = $function;
        $this->recorder = new Recorder();
    }
    
    /**
     * Returns the call recorder.
     *
     * Every call to the mocked function was recorded to this call recorder.
     *
     * @return Recorder The call recorder.
     */
    public function getRecorder()
    {
        return $this->recorder;
    }
    
    /**
     * Enables this mock.
     *
     * @throws MockEnabledException If the function has already an enabled mock.
     * @see Mock::disable()
     * @see Mock::disableAll()
     */
    public function enable()
    {
        $registry = MockRegistry::getInstance();
        if ($registry->isRegistered($this)) {
            throw new MockEnabledException(
                "$this->name is already enabled."
                . "Call disable() on the existing mock."
            );
            
        }
        $this->define();
        $registry->register($this);
    }

    /**
     * Disable this mock.
     *
     * @see Mock::enable()
     * @see Mock::disableAll()
     */
    public function disable()
    {
        MockRegistry::getInstance()->unregister($this);
    }
    
    /**
     * Disable all mocks.
     *
     * @see Mock::enable()
     * @see Mock::disable()
     */
    public static function disableAll()
    {
        MockRegistry::getInstance()->unregisterAll();
    }
    
    /**
     * Calls the mocked function.
     *
     * This method is called from the namespaced function.
     * It also records the call in the call recorder.
     *
     * @param array $arguments the call arguments.
     * @return mixed
     * @internal
     */
    public function call(array $arguments)
    {
        $this->recorder->record($arguments);
        return call_user_func_array($this->function, $arguments);
    }
    
    /**
     * Returns the function name with its namespace.
     *
     * @return String The function name with its namespace.
     * @internal
     */
    public function getCanonicalFunctionName()
    {
        return strtolower("{$this->getNamespace()}\\$this->name");
    }

    /**
     * Returns the namespace without enclosing slashes.
     *
     * @return string The namespace
     */
    private function getNamespace()
    {
        return trim($this->namespace, "\\");
    }
    
    /**
     * Defines the mocked function in the given namespace.
     *
     * In most cases you don't have to call this method. enable() is doing this
     * for you. But if the mock is defined after the first call in the
     * tested class, the tested class doesn't resolve to the mock. This is
     * documented in Bug #68541. You therefore have to define the namespaced
     * function before the first call. Defining the function has no side
     * effects as you still have to enable the mock. If the function was
     * already defined this method does nothing.
     *
     * @see enable()
     * @link https://bugs.php.net/bug.php?id=68541 Bug #68541
     */
    public function define()
    {
        $canonicalFunctionName = $this->getCanonicalFunctionName();
        if (function_exists($canonicalFunctionName)) {
            return;
            
        }
        
        $definition = "
            namespace {$this->getNamespace()};
                
            use malkusch\phpmock\MockRegistry;

            function $this->name()
            {
                \$registry = MockRegistry::getInstance();
                \$mock = \$registry->getMock('$canonicalFunctionName');

                // call the built-in function if the mock was not enabled.
                if (empty(\$mock)) {
                    return call_user_func_array(
                        '$this->name', func_get_args()
                    );
                }

                // call the mock function.
                return \$mock->call(func_get_args());
            }";
        
        eval($definition);
    }
}
