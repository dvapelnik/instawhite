<?php
namespace AppBundle\Instagram;

use Guzzle\Service\Client;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserRetriever implements ContainerAwareInterface
{
    private $storageKey;
    /** @var  ContainerInterface */
    private $container;

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

    public function getUserId($username)
    {
        $userApiData = $this->retrieveUser($username);

        return $userApiData['id'];
    }

    private function retrieveUser($username)
    {
        if (false === ($userApiData = $this->checkInCache($username))) {
            $client = new Client();

            $request = $client->get('https://api.instagram.com/v1/users/search');
            $request->getQuery()
                ->set('q', $username)
                ->set('count', 10);

            if ($this->container->get('session')->get('is_logged')) {
                $request->getQuery()->set(
                    'access_token',
                    $this->container->get('session')->get('instagram')['access_token']
                );
            } else {
                $request->getQuery()->set('client_id', $this->container->getParameter('instagram.client_id'));
            }

            try {
                $response = $request->send();

                $responseApiArray = json_decode($response->getBody(true), true);

                $filteredUsers = array_filter(
                    $responseApiArray['data'],
                    function ($user) use ($username) {
                        return $user['username'] === $username;
                    }
                );

                if (count($filteredUsers) == 1) {
                    $userApiData = current($filteredUsers);

                    $this->addToCache($username, $userApiData);
                } else {
                    throw new UserNotFoundException("User '{$username}' not found");
                }
            } catch (\Exception $e) {
                throw $e;
            }
        }

        return $userApiData;
    }

    private function checkInCache($username)
    {
        $storage = $this->getStorage();

        if (isset($storage[$username])) {
            return $storage[$username];
        }

        return false;
    }

    /**
     * @return mixed
     */
    private function getStorage()
    {
        return $this->container->get('session')->get($this->storageKey, array());
    }

    private function addToCache($username, $userId)
    {
        $storage = $this->getStorage();

        $storage[$username] = $userId;

        $this->updateStorage($storage);
    }

    private function updateStorage($storage)
    {
        $this->container->get('session')->set($this->storageKey, $storage);
    }

    public function getUserData($username)
    {
        return $this->retrieveUser($username);
    }

    /**
     * @param string $storageKey
     *
     * @return UserRetriever
     */
    public function setStorageKey($storageKey)
    {
        $this->storageKey = $storageKey;

        return $this;
    }
}