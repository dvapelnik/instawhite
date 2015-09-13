<?php
namespace AppBundle\Instagram;

use AppBundle\Utils\Cache\SessionCacheInterface;
use AppBundle\Utils\Cache\SessionCacheTrait;
use Guzzle\Service\Client;

class UserRetriever implements SessionCacheInterface
{
    use SessionCacheTrait;

    public function getUserId($username)
    {
        $userApiData = $this->retrieveUser($username);

        return $userApiData['id'];
    }

    private function retrieveUser($username)
    {
        if (false === ($userApiData = $this->getFromCache($username))) {
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

    public function getUserData($username)
    {
        return $this->retrieveUser($username);
    }
}