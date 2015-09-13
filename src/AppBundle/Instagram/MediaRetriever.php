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

    /** @var  ImageComparator */
    private $imageComparator;

    /** @var  int */
    private $count = 16;

    /** @var  string */
    private $source = 'feed';

    private $colorDiffDelta = 150;

    private $userId;

    private $imagesOnly = true;

    private $palette;

    private $usePalette = false;

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
     * @param ImageComparator $imageComparator
     *
     * @return MediaRetriever
     */
    public function setImageComparator($imageComparator)
    {
        $this->imageComparator = $imageComparator;

        return $this;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->count;
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

    /**
     * @param mixed $palette
     *
     * @return MediaRetriever
     */
    public function setPalette($palette)
    {
        $this->palette = $palette;

        return $this;
    }

    /**
     * @param boolean $usePalette
     *
     * @return MediaRetriever
     */
    public function setUsePalette($usePalette)
    {
        $this->usePalette = $usePalette;

        return $this;
    }

    //endregion

    public function getImageLinks()
    {
        $logger = $this->container->get('logger');

        $count = $this->count;
        $resultArray = array();

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
                $logger->info(
                    'Trying API-request',
                    array(
                        'url' => $nextApiUrl,
                    )
                );
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

                // [{'url', 'path', 'color'}]
                $items = array_filter(
                    $items,
                    function ($item) use (&$resultArray, $logger) {
                        $imageUrl = $item['images']['low_resolution']['url'];

                        $logger->info(
                            'Saving image',
                            array(
                                'url' => $imageUrl,
                            )
                        );

                        $savedPath = $this->mediaManager->saveImage($imageUrl);
                        $colorRGB = $this->imageComparator->getImageMainColor($savedPath, true);
                        $colorHex = $this->imageComparator->rgb2hex($colorRGB);

                        $imageArray = array(
                            'url'   => $imageUrl,
                            'path'  => $savedPath,
                            'color' => array(
                                'rgb' => $colorRGB,
                                'hex' => $colorHex,
                            ),
                        );

                        $colorDiff = null;

                        if ($this->usePalette === false || ($colorDiff = $this->imageComparator->getColorDiff(
                                $this->palette,
                                $imageArray['color']['rgb']
                            )) < $this->colorDiffDelta
                        ) {
                            $resultArray[] = $imageArray;

                            return true;
                        }

                        if (null !== $colorDiff) {
                            $logger->info('Color diff', array('color-diff' => $colorDiff));
                        }

                        return false;
                    }
                );

                if (count($responseArray['pagination'])) {
                    $nextApiUrl = $responseArray['pagination']['next_url'];
                } else {
                    break;
                }
            } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
                $_request = $e->getRequest();
                $_response = $e->getResponse();

                if ($_response->getReasonPhrase() === 'BAD REQUEST' &&
                    $_response->getStatusCode() === 400
                ) {

                    if ($_request->getQuery()->get('client_id')) {
                        throw new MayBeNeedAuthException();
                    }

                    throw new AccessDeniedException('Access denied', 0, $e);
                }

                if ($_request->getQuery()->get('client_id') &&
                    $_response->getReasonPhrase() === 'BAD REQUEST' &&
                    $_response->getStatusCode() === 400
                ) {

                }
            } catch (\Exception $e) {
                break;
            }

            $logger->info(
                'Info about collected images',
                array(
                    'collected-count' => count($resultArray),
                )
            );

            $count -= count($items);
        }

        // Save images
        $images = array_slice(
            $resultArray,
            0,
            count($resultArray) > $this->count
                ? $this->count
                : count($resultArray)
        );

        return $images;
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