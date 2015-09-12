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
     * @return MediaSaver
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
        $client = new Client();

        $tmpRoot = implode(
            DIRECTORY_SEPARATOR,
            array(
                $this->container->getParameter('kernel.root_dir'),
                $this->imageTmpFolder,
            )
        );

        $savedImageFullPath = implode(
            DIRECTORY_SEPARATOR,
            array(
                $tmpRoot,
                md5($imageUrl).'.'.pathinfo($imageUrl, PATHINFO_EXTENSION),
            )
        );

        if (file_exists($savedImageFullPath)) {
            return $savedImageFullPath;
        }

        try {
            $client->get($imageUrl)
                ->setResponseBody($savedImageFullPath)->send();

            return $savedImageFullPath;
        } catch (\Exception $e) {
            throw new \Exception('File not saved');
        }
    }
}