<?php
namespace AppBundle\Utils\Cache;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;

interface SessionCacheInterface extends ContainerAwareInterface
{
    public function setStorageKey($storageKey);
}