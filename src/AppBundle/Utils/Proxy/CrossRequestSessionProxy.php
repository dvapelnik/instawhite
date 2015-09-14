<?php
namespace AppBundle\Utils\Proxy;

use AppBundle\Utils\Cache\SessionCacheInterface;
use AppBundle\Utils\Cache\SessionCacheTrait;

class CrossRequestSessionProxy implements SessionCacheInterface
{
    use SessionCacheTrait;

    public function setObject($object)
    {
        $key = $this->getKey($object);

        if ($this->hasKey($key)) {
            return $key;
        }

        $this->addToCache($key, $this->dehydrate($object));

        return $key;
    }

    protected function getKey($object)
    {
        return md5($this->dehydrate($object));
    }

    protected function dehydrate($object)
    {
        return serialize($object);
    }

    public function getObject($key)
    {
        if ($this->hasKey($key)) {
            $object = $this->hydrate($this->getFromCache($key));

            return $object;
        }

        return false;
    }

    protected function hydrate($string)
    {
        return unserialize($string);
    }
}