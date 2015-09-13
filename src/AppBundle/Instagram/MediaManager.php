<?php
namespace AppBundle\Instagram;

use Guzzle\Service\Client;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaManager implements ContainerAwareInterface
{
    /** @var  ContainerInterface */
    private $container;

    private $imageTmpFolder;

    private $savePath;

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
     * @param mixed $savePath
     *
     * @return MediaManager
     */
    public function setSavePath($savePath)
    {
        $this->savePath = $savePath;

        return $this;
    }

    /**
     * @param mixed $imageTmpFolder
     *
     * @return MediaManager
     */
    public function setImageTmpFolder($imageTmpFolder)
    {
        $this->imageTmpFolder = $imageTmpFolder;

        return $this;
    }

    public function saveImage($imageUrl)
    {
        return $this->doSaveImage($imageUrl, $this->getDestinationFileName($imageUrl));
    }

    private function doSaveImage($source, $destination)
    {

        if (file_exists($destination)) {
            return $destination;
        }

        try {
            $this->makeSaveImageRequest($source, $destination)->send();

            return $destination;
        } catch (\Exception $e) {
            throw new \Exception('File not saved');
        }

    }

    private function makeSaveImageRequest($source, $destination)
    {
        return (new Client())->get($source)->setResponseBody($destination);
    }

    private function getDestinationFileName($imageUrl)
    {
        $tmpRoot = implode(
            DIRECTORY_SEPARATOR,
            array(
                $this->container->getParameter('kernel.root_dir'),
                $this->imageTmpFolder,
            )
        );

        $saveImageFullPath = implode(
            DIRECTORY_SEPARATOR,
            array(
                $tmpRoot,
                md5($imageUrl).'.'.pathinfo($imageUrl, PATHINFO_EXTENSION),
            )
        );

        return $saveImageFullPath;
    }

    public function saveImages($imageUrls)
    {
        $savedImagePaths = array();

        $requests = array_map(
            function ($imageUrl) use (&$savedImagePaths) {
                $destination = $this->getDestinationFileName($imageUrl);

                $savedImagePaths[] = array(
                    'url'  => $imageUrl,
                    'path' => $destination,
                );

                return $this->makeSaveImageRequest($imageUrl, $destination);
            },
            array_filter(
                $imageUrls,
                function ($imageUrl) use (&$savedImagePaths) {
                    $destination = $this->getDestinationFileName($imageUrl);
                    if (file_exists($destination)) {
                        $savedImagePaths[] = array(
                            'url'  => $imageUrl,
                            'path' => $destination,
                        );

                        return false;
                    }

                    return true;
                }
            )
        );

        (new Client())->send($requests);

        return $savedImagePaths;
    }
}