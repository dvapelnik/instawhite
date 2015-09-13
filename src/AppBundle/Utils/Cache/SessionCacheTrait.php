<?php
namespace AppBundle\Utils\Cache;

use Symfony\Component\DependencyInjection\ContainerInterface;

trait SessionCacheTrait
{
    protected $storageKey;

    /** @var  ContainerInterface */
    protected $container;

    public function setStorageKey($storageKey)
    {
        $this->storageKey = $storageKey;

        return $this;
    }

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    protected function getFromCache($key)
    {
        $storage = $this->getStorage();

        if (isset($storage[$key])) {
            return $storage[$key];
        }

        return false;
    }

    /**
     * @return mixed
     */
    protected function getStorage()
    {
        return $this->container->get('session')->get($this->storageKey, array());
    }

    protected function addToCache($key, $value)
    {
        $storage = $this->getStorage();

        $storage[$key] = $value;

        $this->updateStorage($storage);
    }

    protected function updateStorage($storage)
    {
        $this->container->get('session')->set($this->storageKey, $storage);
    }
}