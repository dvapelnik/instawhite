<?php
namespace AppBundle\Instagram;

use Guzzle\Service\Client;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaRetriever implements ContainerAwareInterface
{
    /** @var  Container */
    private $container;

    /** @var  MediaManager */
    private $mediaManager;

    /** @var  int */
    private $count = 16;

    /** @var  string */
    private $source = 'feed';

    /** @var  string */
    private $accessToken;

    private $userId;

    private $imagesOnly = true;

    private $itemsPerRequestLimit = 30;

    private $apiUrlPrefix = 'https://api.instagram.com/v1';
    private $apiMediaUrlSuffix = '/users/{user-id}/media/recent';
    private $apiFeedUrlSuffix = '/users/self/feed';

    //region Setters
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

    /**
     * @param MediaManager $mediaManager
     *
     * @return MediaRetriever
     */
    public function setMediaManager($mediaManager)
    {
        $this->mediaManager = $mediaManager;

        return $this;
    }

    /**
     * @param int $count
     *
     * @return MediaRetriever
     */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @param string $source
     *
     * @return MediaRetriever
     */
    public function setSource($source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @param boolean $imagesOnly
     *
     * @return MediaRetriever
     */
    public function setImagesOnly($imagesOnly)
    {
        $this->imagesOnly = $imagesOnly;

        return $this;
    }

    /**
     * @param mixed $userId
     *
     * @return MediaRetriever
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    //endregion

    public function getImageLinks()
    {
        $count = $this->count;
        $mediaManager = $this->mediaManager;
        $resultArray = array();

        $isFullyRequested = true;

        $client = new Client($this->getRequestUrl());

        $request = $client->createRequest('GET');
        $request->getQuery()->set('count', $this->itemsPerRequestLimit);

        if ($this->container->get('session')->get('is_logged')) {
            $request->getQuery()->set(
                'access_token',
                $this->container->get('session')->get('instagram')['access_token']
            );
        } else {
            $request->getQuery()->set(
                'client_id',
                $this->container->getParameter('instagram.client_id')
            );
        }

        $nextApiUrl = $request->getUrl();

        while ($count > 0) {
            try {
                $response = $client->get($nextApiUrl)->send();

                $responseArray = json_decode($response->getBody(true), true);

                $items = $responseArray['data'];

                if ($this->imagesOnly) {
                    $items = array_filter(
                        $items,
                        function ($item) {
                            return $item['type'] == 'image';
                        }
                    );
                }

                $resultArray = array_merge(
                    $resultArray,
                    array_map(
                        function ($item) {
                            return $item['images']['low_resolution']['url'];
                        },
                        $items
                    )
                );

                if (count($responseArray['pagination'])) {
                    $nextApiUrl = $responseArray['pagination']['next_url'];
                } else {
                    $isFullyRequested = false;

                    break;
                }
            } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                $_request = $e->getRequest();
                $_response = $e->getResponse();

                if ($_request->getQuery()->get('client_id') &&
                    $_response->getReasonPhrase() === 'BAD REQUEST' &&
                    $_response->getStatusCode() === 400
                ) {
                    throw new MayBeNeedAuthException();
                }
            } catch (\Exception $e) {
                break;
            }

            $count -= count($items);
        }

        // Save images
        $imageUrls = array_slice(
            $resultArray,
            0,
            count($resultArray) > $this->count
                ? $this->count
                : count($resultArray)
        );

        $savedImageFullPaths = $mediaManager->saveImages($imageUrls);

        return array(
            $imageUrls,
            $isFullyRequested,
        );
    }

    private function getRequestUrl()
    {
        return implode(
            '',
            array(
                $this->apiUrlPrefix,
                $this->source == 'feed'
                    ? $this->apiFeedUrlSuffix
                    : str_replace('{user-id}', $this->userId, $this->apiMediaUrlSuffix),
            )
        );
    }


}