<?php
namespace AppBundle\Instagram;

use Guzzle\Service\Client;

class MediaRetriever
{
    /** @var  int */
    private $count;

    /** @var  string */
    private $source;

    /** @var  string */
    private $accessToken;

    private $userId;

    private $imagesOnly;

    private $itemsPerRequestLimit = 30;

    private $apiUrlPrefix = 'https://api.instagram.com/v1';
    private $apiMediaUrlSuffix = '/users/{user-id}/media/recent';
    private $apiFeedUrlSuffix = '/users/self/feed';

    /**
     * MediaRetriever constructor.
     *
     * @param int $count
     * @param string $source
     * @param $accessToken
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct($count, $source, $accessToken, $options = array())
    {
        $this->count = $count;
        $this->source = $source;
        $this->accessToken = $accessToken;

        $this->userId = isset($options['user-id']) ? $options['user-id'] : null;
        $this->imagesOnly = isset($options['imagesOnly']) ? $options['imagesOnly'] : true;

        if ($this->source === 'media' && $this->userId === null) {
            throw new \Exception("Option 'user-id' is required when source is '{$this->source}'");
        }
    }

    public function getImageLinks()
    {
        $count = $this->count;
        $resultArray = array();

        $client = new Client($this->getRequestUrl());

        $request = $client->createRequest('GET');
        $request->getQuery()
            ->set('access_token', $this->accessToken)
            ->set('count', $this->itemsPerRequestLimit);

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

                $nextApiUrl = $responseArray['pagination']['next_url'];
            } catch (\Exception $e) {
                break;
            }

            $count -= count($items);
        }

        return array_slice($resultArray, 0, $this->count);
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