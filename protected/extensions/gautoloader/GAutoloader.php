<?php

// Credit: mindplay-dk https://gist.github.com/4234540

    /**
     * Simple autoloader mapping namespaces to paths.
     *
     * @property array $namespaces hash where namespace => absolute root-path
     * @property array $classes hash where fully qualified class-name => absolute path
     */
class GAutoloader
{
    /**
     * @var array hash where namespace => absolute root-path
     */
    private $_ns = array();

    /**
     * @var array hash where fully qualified class-name => absolute path
     */
    private $_map = array();

    /**
     * @var string|null absolute default root-path for classes with no mapping; or null, if no default
     */
    public $root = null;

    /**
     * Maps a namespace to a path.
     *
     * @param string $namespace fully qualified namespace
     * @param string $path absolute root-path
     * @return self
     */
    public function addNamespace($namespace, $path)
    {
        if (is_dir($path) === false) {
            throw new Exception("path not found: $path");
        }

        $this->_ns[$namespace] = $path;
        
       //echo '   - adding '.$namespace.' at '.$path.PHP_EOL;

        return $this;
    }

    /**
     * Maps a class-name to an absolute path.
     *
     * @param string $classname fully qualified class-name
     * @param string $path absolute path to PHP class-file
     * @return self
     */
    public function addClass($classname, $path)
    {
        $this->_map[$classname] = $path;
        
          //echo '['.get_class().'] - adding '.$classname.' at '.$path.PHP_EOL;
        return $this;
    }

    /**
     * Map a class-name to a path using registered namespace and class paths.
     *
     * @param string $name fully qualified class-name
     * @return string|null unchecked path to PHP class file; or null, if no root is configured
     */
    public function map($name)
    {
        if (isset($this->_map[$name])) {
            return $this->_map[$name]; // class-name is registered
        }

        $part = $name;

        while ($offset = strrpos($part, '\\')) {
            $part = substr($part, 0, $offset);
            if (isset($this->_ns[$part])) {
                return $this->_ns[$part] . DIRECTORY_SEPARATOR . strtr(substr($name, 1+strlen($part)), '\\', DIRECTORY_SEPARATOR) . '.php';
            }
        }

        return ($this->root !== null)
            ? $this->root . DIRECTORY_SEPARATOR . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php'
            : null;
    }

    /**
     * Callback for SPL autoloader
     *
     * @return bool true if the class was autoloaded, otherwise false
     * @see spl_autoload_register()
     */
    public function load($name)
    {
        $path = $this->map($name);

        if (($path===null) || (file_exists($path)===false)) {
            return false;
        }

        @include_once($path);
        $ret = class_exists($name, false);
        
        if ($ret)
           //echo '['.get_class().'] - loaded '.$name.PHP_EOL;

        return $ret;
    }
}