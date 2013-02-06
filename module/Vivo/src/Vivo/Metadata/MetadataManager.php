<?php
namespace Vivo\Metadata;

use Vivo\Module\ResourceManager\ResourceManager;
use Vivo\Module\ModuleNameResolver;
use Vivo\Module\Exception\ResourceNotFoundException;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Config\Reader\Ini as ConfigReader;
use Zend\Config\Config;

/**
 * MetadataManager
 */
class MetadataManager
{
    /**
     * @var \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceManager;

    /**
     * @var \Vivo\Module\ResourceManager\ResourceManager
     */
    protected $resourceManager;

    /**
     * @var \Vivo\Module\ModuleNameResolver
     */
    protected $moduleNameResolver;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var array
     */
    protected $cache = array(
        'rawmeta' => array(),
        'meta' => array()
    );

    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceManager
     * @param \Vivo\Module\ResourceManager\ResourceManager $resourceManager
     * @param \Vivo\Module\ModuleNameResolver $moduleNameResolver
     * @param array $options
     */
    public function __construct(
            ServiceLocatorInterface $serviceManager,
            ResourceManager $resourceManager,
            ModuleNameResolver $moduleNameResolver,
            array $options = array())
    {
        $this->options = $options;
        $this->serviceManager = $serviceManager;
        $this->resourceManager = $resourceManager;
        $this->moduleNameResolver = $moduleNameResolver;
    }

    /**
     * Returns raw metadata
     * @param string $entityClass
     * @return array
     */
    public function getRawMetadata($entityClass) {
        if(isset($this->cache['rawmeta'][$entityClass])) {
            return $this->cache['rawmeta'][$entityClass];
        }

        $config = new Config(array());
        $reader = new ConfigReader();

        $parent = $entityClass;
        $parents = array($parent);
        while(($parent = get_parent_class($parent)) && $parent !== false) {
            $parents[] = $parent;
        }
        $parents = array_reverse($parents);

        foreach ($parents as $class) {
            // Vivo CMS model, other is a module model
            if(strpos($class, 'Vivo\\') === 0) {
                $path = realpath(sprintf('%s/%s.ini',
                    $this->options['config_path'],
                    str_replace('\\', DIRECTORY_SEPARATOR, $class))
                );

                if($path) {
                    $resource = file_get_contents($path);
                    $entityConfig = $reader->fromString($resource);
                    $entityConfig = new Config($entityConfig);

                    $config = $config->merge($entityConfig);
                }
            }
            else {
                $moduleName = $this->moduleNameResolver->fromFqcn($class);
                $path = sprintf('%s.ini', str_replace('\\', DIRECTORY_SEPARATOR, $class));

                try {
                    $resource = $this->resourceManager->getResource($moduleName, $path, 'metadata');
                    $entityConfig = $reader->fromString($resource);
                    $entityConfig = new Config($entityConfig);

                    $config = $config->merge($entityConfig);
                }
                catch (ResourceNotFoundException $e) { }
            }
        }

        $descriptors = $config->toArray();

        uasort($descriptors, function($a, $b) {
            return (isset($a['order']) ? intval($a['order']) : 0) > (isset($b['order']) ? intval($b['order']) : 0);
        });

        $this->cache['rawmeta'][$entityClass] = $descriptors;

        return $descriptors;
    }

    /**
     * Returns metadata
     * @param string $entityClass
     * @return array
     */
    public function getMetadata($entityClass) {
        if(isset($this->cache['meta'][$entityClass])) {
            return $this->cache['meta'][$entityClass];
        }

        $config = $this->getRawMetadata($entityClass);
        $this->applyProvider($entityClass, $config);
        $this->cache['meta'][$entityClass] = $config;

        return $config;
    }

    /**
     * Applies metadata provider for all classes defined in config.
     *
     * @param string $entityClass
     * @param array $config
     * @throws Exception\DescriptiorException
     */
    private function applyProvider($entityClass, &$config) {
        foreach ($config as $key => &$value) {
            if(is_array($value)) {
                $this->applyProvider($entityClass, $value);
            }
            elseif (strpos($value, '\\')) {
                if(class_exists($value) && is_subclass_of($value, 'Vivo\Metadata\MetadataValueProviderInterface')) {
                    /** @var $provider MetadataValueProviderInterface */
                    $provider = new $value($this->serviceManager);
                    $value    = $provider->getValue($entityClass);
                }
                else {
                    throw new Exception\DescriptiorException(
                        sprintf('Metadata value provider \'%s\' defined in metadata %s::%s is not an instance of '
                            .'Vivo\Metadata\MetadataValueProviderInterface', $value, get_class($entityClass), $key)
                    );
                }
            }
        }
    }
}
