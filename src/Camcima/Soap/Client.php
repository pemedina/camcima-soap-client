<?php

namespace Camcima\Soap;

use Camcima\Exception\InvalidClassMappingException;
use Camcima\Exception\InvalidParameterException;
use Camcima\Exception\MissingClassMappingException;

/**
 * Soap Client
 *
 * @author Carlos Cima
 */
class Client extends \SoapClient
{
    /**
     * Default Values
     */
    const DEFAULT_USER_AGENT = 'CamcimaSoapClient/1.0';
    const DEFAULT_PROXY_HOST = 'localhost';
    const DEFAULT_PROXY_PORT = 8888;

    /**
     * User Agent
     * 
     * @var string
     */
    protected $userAgent;

    /**
     * cURL Options
     * 
     * @var array 
     */
    protected $curlOptions;

    /**
     * Proxy Host
     * 
     * @var string 
     */
    protected $proxyHost;

    /**
     * Proxy Port
     * 
     * @var int 
     */
    protected $proxyPort;

    /**
     * Lowercase first character of the root element name
     * 
     * @var boolean 
     */
    protected $lowerCaseFirst;

    /**
     * Keep empty object properties when building the request parameters
     *  
     * @var boolean 
     */
    protected $keepNullProperties;

    /**
     * Debug Mode
     * 
     * @var boolean 
     */
    protected $debug;

    /**
     * Constructor
     * 
     * @param string $wsdl
     * @param array $options
     */
    function __construct($wsdl, array $options = array())
    {
        parent::__construct($wsdl, $options);
        $this->curlOptions = array();
        $this->lowerCaseFirst = false;
        $this->keepNullProperties = true;
        $this->debug = false;
    }

    /**
     * {@inheritDoc}
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $userAgent = $this->userAgent ? : self::DEFAULT_USER_AGENT;

        $headers = array(
            'Connection: Close',
            'User-Agent: ' . $userAgent,
            'Content-Type: text/xml',
            'SOAPAction: "' . $action . '"',
            'Expect:'
        );

        $curlOptions = $this->getCurlOptions();
        $curlOptions[CURLOPT_POSTFIELDS] =
            is_object($request) ?
            $this->getSoapVariables($request, $this->lowerCaseFirst, $this->keepNullProperties) :
            $request;
        $curlOptions[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init($location);
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);

        return $response;
    }

    /**
     * Maps Result XML Elements to Classes
     * 
     * @param \stdClass $soapResult
     * @param array $resultClassMap
     * @return object
     */
    public function mapSoapResult($soapResult, $rootClassName, array $resultClassMap = array(), $resultClassNamespace = '')
    {
        $objVarsNames = array_keys(get_object_vars($soapResult));
        $rootClassName = reset($objVarsNames);
        $soapResultObj = $this->mapObject($soapResult->$rootClassName, $rootClassName, $resultClassMap, $resultClassNamespace);

        return $soapResultObj;
    }

    /**
     * Set cURL Options
     * 
     * @param array $curlOptions
     * @return \Camcima\Soap\Client
     */
    public function setCurlOptions(array $curlOptions)
    {
        $this->curlOptions = $curlOptions;
        return $this;
    }

    /**
     * Set User Agent
     * 
     * @param string $userAgent
     * @return \Camcima\Soap\Client
     */
    public function setUserAgent($userAgent = self::DEFAULT_USER_AGENT)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Lowercase first character of the root element name
     * 
     * Defaults to false
     * 
     * @param boolean $lowerCaseFirst
     * @return \Camcima\Soap\Client
     */
    public function setLowerCaseFirst($lowerCaseFirst)
    {
        $this->lowerCaseFirst = $lowerCaseFirst;
        return $this;
    }

    /**
     * Keep null object properties when building the request parameters
     * 
     * Defaults to true
     * 
     * @param boolean $keepNullProperties
     * @return \Camcima\Soap\Client
     */
    public function setKeepNullProperties($keepNullProperties)
    {
        $this->keepNullProperties = $keepNullProperties;
        return $this;
    }

    /**
     * Set Debug Mode
     * 
     * @param boolean $debug
     * @return \Camcima\Soap\Client
     */
    public function setDebug($debug = false)
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Use Proxy
     * 
     * @param string $host
     * @param int $port
     * @return \Camcima\Soap\Client
     */
    public function useProxy($host = self::DEFAULT_PROXY_HOST, $port = self::DEFAULT_PROXY_PORT)
    {
        $this->proxyHost = $host;
        $this->proxyPort = $port;
        return $this;
    }

    /**
     * Merge Curl Options
     * 
     * @return array
     */
    public function getCurlOptions()
    {
        $mandatoryOptions = array(
            CURLOPT_POST => true,
            CURLOPT_HEADER => false
        );

        $defaultOptions = array(
            CURLOPT_VERBOSE => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        );

        $mergedArray = $mandatoryOptions + $this->curlOptions + $defaultOptions;

        if (strlen($this->proxyHost) > 0) {
            if (strlen($this->proxyPort) > 0) {
                $proxyPort = $this->proxyPort;
            } else {
                $proxyPort = 8888;
            }
            $mergedArray[CURLOPT_PROXY] = $this->proxyHost;
            $mergedArray[CURLOPT_PROXYPORT] = $proxyPort;
        }

        return $mergedArray;
    }

    /**
     * Get SOAP Request Variables
     * 
     * Prepares request parameters to be
     * sent in the SOAP Request Body.
     * 
     * @param object $requestObject
     * @param boolean $lowerCaseFirst Lowercase first character of the root element name
     * @param boolean $keepNullProperties Keep null object properties when building the request parameters
     * @return array
     */
    public function getSoapVariables($requestObject, $lowerCaseFirst = false, $keepNullProperties = true)
    {
        if (!is_object($requestObject)) {
            throw new InvalidParameterException('Parameter requestObject is not an object');
        }
        $objectName = $this->getClassNameWithoutNamespaces($requestObject);
        if ($lowerCaseFirst) {
            $objectName = lcfirst($objectName);
        }
        $stdClass = new \stdClass();
        $stdClass->$objectName = $requestObject;

        return $this->objectToArray($stdClass, $keepNullProperties);
    }

    protected function getClassNameWithoutNamespaces($object)
    {
        $class = explode('\\', get_class($object));
        return end($class);
    }

    /**
     * Convert Objet to Array
     * 
     * This method omits null value properties
     * 
     * @param mixed $obj
     * @param boolean $keepNullProperties Keep null object properties when building the request parameters
     * @return array
     */
    protected function objectToArray($obj, $keepNullProperties = true)
    {
        $arrObj = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($arrObj as $key => $val) {
            $val = (is_array($val) || is_object($val)) ? $this->objectToArray($val, $keepNullProperties) : $val;
            if ($keepNullProperties || $val !== null) {
                $val = ($val === null) ? $val = '' : $val;
                $arr[$key] = is_scalar($val) ? ((string) $val) : $val;
            }
        }
        return $arr;
    }

    /**
     * Map Remote SOAP Objects(stdClass) to local classes
     * 
     * @param mixed $obj Remote SOAP Object
     * @param string $className Root (or current) class name
     * @param array $classMap Class Mapping
     * @param string $classNamespace Namespace where the local classes are located
     * @return \Camcima\Soap\mappedClassName
     * @throws MissingClassMappingException
     * @throws InvalidClassMappingException
     */
    protected function mapObject($obj, $className, $classMap = array(), $classNamespace = '')
    {
        if (is_object($obj)) {

            // Check if there is a mapping.
            if (isset($classMap[$className])) {
                $mappedClassName = $classMap[$className];
            } else {
                if ($classNamespace) {
                    $mappedClassName = str_replace('\\\\', '\\', $classNamespace . '\\' . $className);
                } else {
                    throw new MissingClassMappingException('Missing mapping for element "' . $className . '"');
                }
            }

            // Check if local class exists.
            if (!class_exists($mappedClassName)) {
                throw new InvalidClassMappingException('Class not found: "' . $mappedClassName . '"');
            }
            // Get class properties and methods.
            $objProperties = array_keys(get_class_vars($mappedClassName));
            $objMethods = get_class_methods($mappedClassName);

            // Instantiate new mapped object.
            $objInstance = new $mappedClassName();

            // Map remote object to local object.
            $arrObj = get_object_vars($obj);
            foreach ($arrObj as $key => $val) {
                $useSetter = false;
                if (in_array('set' . $key, $objMethods)) {
                    $useSetter = true;
                } elseif (!in_array($key, $objProperties)) {
                    throw new InvalidClassMappingException('Property "' . $mappedClassName . '::' . $key . '" doesn\'t exist');
                }

                // If it's not scalar, recursive call the mapping function
                if (is_array($val) || is_object($val)) {
                    $val = $this->mapObject($val, $key, $classMap, $classNamespace);
                }

                // If there is a setter, use it. If not, set the property directly.
                if ($useSetter) {
                    $setterName = 'set' . $key;

                    // Check if parameter is \DateTime
                    $reflection = new \ReflectionMethod($mappedClassName, $setterName);
                    $params = $reflection->getParameters();
                    if (count($params) != 1) {
                        throw new InvalidClassMappingException('Wrong Argument Count in Setter for property ' . $key);
                    }
                    $param = reset($params);
                    /* @var $param \ReflectionParameter */
                    $paramClassName = $param->getClass()->getNamespaceName() . '\\' . $param->getClass()->getName();

                    // If setter parameter is typehinted, cast the value before calling the method
                    if ($paramClassName == '\DateTime') {
                        $val = new \DateTime($val);
                    }

                    $objInstance->$setterName($val);
                } else {
                    $objInstance->$key = $val;
                }
            }
            return $objInstance;
        } elseif (is_array($obj)) {
            // Value is array.
            $returnArray = array();
            // If array mapping exists, map array elements.
            if (array_key_exists('array|' . $className, $classMap)) {
                $className = 'array|' . $className;
                foreach ($obj as $key => $val) {
                    $returnArray[$key] = $this->mapObject($val, $className, $classMap, $classNamespace);
                }
            } else {
                // If array mapping doesn't exist, return the array.
                $returnArray = $obj;
            }
            return $returnArray;
        } else {
            // Value is scalar. Just return it.
            return $obj;
        }
    }
}