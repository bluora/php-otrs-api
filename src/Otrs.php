<?php

namespace Bluora\Otrs;

class Otrs
{
    /**
     * Tracing to enable getLast* methods.
     *
     * @var boolean
     */
    private static $trace = false;

    /**
     * The standard OTRS rpc file.
     *
     * @var boolean
     */
    private static $rpc = 'rpc.pl';

    /**
     * SOAP Connection.
     *
     * @var SoapClient
     */
    private $connection = false;

    /**
     * Remote Perl Module.
     *
     * @var string
     */
    private $module = false;

    /**
     * Data to be sent with request.
     *
     * @var array
     */
    private $data;

    /**
     * Username.
     *
     * @var string
     */
    private $location;

    /**
     * Uri.
     *
     * @var string
     */
    private $uri = 'Core';

    /**
     * Username.
     *
     * @var string
     */
    private $username;

    /**
     * Password.
     *
     * @var string
     */
    private $password;

    /**
     * Create an instance of the client and try connecting to OTRS.
     *
     * @param mixed $location
     * @param mixed $username
     * @param mixed $password
     * @param mixed $uri
     *
     * @return Otrs
     */
    public function __construct($module = '')
    {
        $this->setModule($module);
        $this->setEnv('location');
        $this->setEnv('uri');
        $this->setEnv('username');
        $this->setEnv('password');
        return $this;
    }

    private function createConnection()
    {
        foreach (['location', 'uri', 'username', 'password'] as $field) {
            if (empty($this->$field)) {
                throw new \Exception('Required `'.$field.'` are missing.');
            }
        }

        $this->connection = new \SoapClient(null, [
            'location'   => $this->location . static::$rpc,
            'uri'        => $this->uri,
            'trace'      => static::$trace,
            'login'      => $this->username,
            'password'   => $this->password,
            'style'      => SOAP_RPC,
            'use'        => SOAP_ENCODED
        ]);

        return $this;
    }

    /**
     * Enable or disable the trace.
     *
     * @param boolean $true_or_false
     */
    public static function setTrace($true_or_false)
    {
        static::$trace = $true_or_false;
    }

    /**
     * Change the default rpc.pl name.
     *
     * @param boolean $name
     */
    public static function setRpc($name)
    {
        static::$rpc = $name;
    }

    public function setEnv($env_name)
    {
        $method = 'setSoap'.ucfirst(strtolower($env_name));
        if (method_exists($this, $method)) {
            $set_value = (getenv('OTRS_API_'.strtoupper($env_name))) ? getenv('OTRS_API_'.strtoupper($env_name)) : '';
            if (!empty($set_value)) {
                return $this->$method($set_value);
            }
        }
        return $this;
    }

    /**
     * Set the module.
     *
     * @param string $module
     *
     * @return Otrs
     */
    public function setModule($module)
    {
        if (!empty($module)) {
            $this->module = $module.'Object';
        } else {
            $this-$module = '';
        }
        return $this;
    }

    /**
     * Set the location.
     *
     * @param string $location
     *
     * @return Otrs
     */
    public function setSoapLocation($location)
    {
        $this->location = $location;
        $this->connection = false;
        return $this;
    }

    /**
     * Set the username.
     *
     * @param string $username
     *
     * @return Otrs
     */
    public function setSoapUsername($username)
    {
        $this->username = $username;
        $this->connection = false;
        return $this;
    }

    /**
     * Set the password.
     *
     * @param string $password
     *
     * @return Otrs
     */
    public function setSoapPassword($password)
    {
        $this->password = $password;
        $this->connection = false;
        return $this;
    }

    /**
     * Set the uri.
     *
     * @param string $uri
     *
     * @return Otrs
     */
    public function setSoapUri($uri)
    {
        $this->uri = $uri;
        $this->connection = false;
        return $this;
    }

    /**
     * Set a value for the request.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return Otrs
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get the data and return in format for the dispatch.
     *
     * @return array
     */
    private function getData()
    {
        $result = [];
        foreach ($this->data as $key => $value) {
            $result[] = $key;
            $result[] = $value;
        }
        return $result;
    }

    /**
     * Dispatch the soap call.
     *
     * @param  string $name
     * @return result
     */
    private function dispatch($name)
    {
        if (empty($this->connection)) {
            $this->createConnection();
        }

        $payload = $this->getData();

        array_unshift(
            $payload,
            $this->username,
            $this->password,
            $this->module,
            $name
        );

        $result = $this->connection->__soapCall('Dispatch', $payload);

        if (!empty($result) && is_array($result)) {
            $assoc_result = [];
            $result = array_values($result);
            for ($i = 0; $i < count($result); $i = $i + 2) {
                $assoc_result[$result[$i]] = $result[$i + 1];
            }
            return $assoc_result;
        }
        
        return [];
    }

    public function reset()
    {
        $this->data = [];
    }

    public function __call($name, $arguments)
    {
        if (isset($arguments[0]) && is_array($arguments[0])) {
            foreach ($arguments[0] as $key => $value) {
                $this->set($key, $value);
            }
        }
        return $this->dispatch(ucfirst($name));
    }
}
