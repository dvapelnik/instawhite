<?php
namespace AppBundle\Instagram;

use AppBundle\Instagram\Filler\RandomFiller;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CollageMaker
{
    private $rotateDegreeDelta = 10;

    private $size;

    private $defaultImageSize;

    private $images;

    /** @var RandomFiller CollageFillerInterface */
    private $filler;

    /**
     * CollageMaker constructor.
     *
     * @param $size
     * @param $images
     * @param $filler
     * @param int $defaultImageSize
     */
    public function __construct($size = null, $images, $filler, $defaultImageSize = 100)
    {
        $this->size = $size;
        $this->images = $images;
        $this->filler = $filler;
        $this->defaultImageSize = $defaultImageSize;

        $this->check();
    }

    private function check()
    {
        $message = null;

        if (intval($this->size) != $this->size) {
            $message = "'size' value should be an integer";
        }

        if (!is_array($this->images) || array_reduce(
                $this->images,
                function ($carry, $imageData) {
                    return
                        $carry &&
                        isset($imageData['url']) &&
                        isset($imageData['path']) &&
                        isset($imageData['color']) &&
                        isset($imageData['color']['rgb']) &&
                        isset($imageData['color']['hex']);
                },
                true
            ) === false
        ) {
            $message = "'images' value should be an array looks like [{url, path, color: {rgb, hex}}]";
        }

        if ($message !== null) {
            throw new \InvalidArgumentException($message);
        }
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
        // TODO: Implement setContainer() method.
    }

    /**
     * @return resource
     */
    public function makeCollage()
    {
        shuffle($this->images);

        /** @var \Imagick[] $images */
        $images = array_map(
            function ($imageData) {
                return new \Imagick($imageData['path']);
            },
            $this->images
        );

        if ($this->size === null) {
            $this->size = sqrt($this->getCountOfCells($images)) * $this->defaultImageSize;
        }

        $canvas = new \Imagick();
        $canvas->newImage($this->size, $this->size, new \ImagickPixel('black'));
        $canvas->setImageFormat('png');

        $background = new \Imagick($this->images[rand(0, count($this->images) - 1)]['path']);
        $background->scaleImage(max($this->size, $this->size), max($this->size, $this->size), true);
        $background->blurImage(10, 10);
        $background->setColorspace(\Imagick::COLOR_BLACK);

        $canvas->compositeImage($background, \Imagick::COMPOSITE_OVER, 0, 0);

        $gridWidthInCells = sqrt($this->getCountOfCells($images));
        $imageSize = ceil($this->size / sqrt($this->getCountOfCells($images)));
        $borderWidth = ceil($imageSize * 5 / 100);

        foreach ($images as &$image) {
            $image->scaleImage(
                ceil($this->size / $gridWidthInCells) - $borderWidth * 2,
                $image->getImageHeight(),
                true
            );
            $image->borderImage(new \ImagickPixel('white'), $borderWidth, $borderWidth);
        }
        unset ($image);


        $countByX = $countByY = $gridWidthInCells;

        $imagesForCollageData = array();

        //region Prepare images to collage
        $this->filler->fillPreparedCollage(
            $imagesForCollageData,
            array(
                'size'              => $this->size,
                'countByX'          => $countByX,
                'countByY'          => $countByY,
                'images'            => $images,
                'imageSize'         => $imageSize,
                'rotateDegreeDelta' => $this->rotateDegreeDelta,
            )
        );
        //endregion

        $imagesForCollage = array_map(
            function ($imageForCollage) {
                return $imageForCollage['image'];
            },
            $imagesForCollageData
        );

        $temporaryCanvas = $this->makeTemporaryCollage(
            $imagesForCollageData,
            array(
                $this->size + $this->getWidthAddition($imagesForCollage, $imageSize),
                $this->size + $this->getHeightAddition($imagesForCollage, $imageSize),
            )
        );

        $canvas->compositeImage(
            $temporaryCanvas,
            \Imagick::COMPOSITE_OVER,
            ($canvas->getImageWidth() - $temporaryCanvas->getImageWidth()) / 2,
            ($canvas->getImageWidth() - $temporaryCanvas->getImageHeight()) / 2
        );

        return $canvas;
    }

    private function getCountOfCells($images)
    {
        return pow(ceil(sqrt(count($images))), 2);
    }

    private function makeTemporaryCollage($imagesForCollageData, $temporaryCanvasSize)
    {
        $temporaryCanvas = new \Imagick();
        $temporaryCanvas->newImage(
            $temporaryCanvasSize[0],
            $temporaryCanvasSize[1],
            new \ImagickPixel('none')
        );
        $temporaryCanvas->setBackgroundColor(new \ImagickPixel('none'));
        $temporaryCanvas->setImageFormat('png');

        foreach ($imagesForCollageData as $imageItem) {
            $temporaryCanvas->compositeImage(
                $imageItem['image'],
                \Imagick::COMPOSITE_OVER,
                $imageItem['point'][0],
                $imageItem['point'][1]
            );
        }

        $temporaryCanvas->scaleImage(
            $temporaryCanvas->getImageWidth() * 0.9,
            $temporaryCanvas->getImageHeight() * 0.9,
            true
        );

        return $temporaryCanvas;
    }

    private function getWidthAddition($images, $imageSize)
    {
        $filteredImages = array();

        foreach (array_values($images) as $key => $image) {
            if (($key + 1) % sqrt(count($images))) {
                $filteredImages[] = $image;
            }
        }

        $maxAddition = array_reduce(
            $filteredImages,
            function ($carry, $image) use ($imageSize) {
                /** @var \Imagick $image */
                return $carry < $image->getImageWidth() - $imageSize
                    ? $image->getImageWidth() - $imageSize
                    : $carry;
            },
            0
        );

        return $maxAddition;
    }

    private function getHeightAddition($images, $imageSize)
    {
        $filteredImages = array_slice($images, -sqrt(count($images)));

        $maxAddition = array_reduce(
            $filteredImages,
            function ($carry, $image) use ($imageSize) {
                /** @var \Imagick $image */
                return $carry < $image->getImageWidth() - $imageSize
                    ? $image->getImageHeight() - $imageSize
                    : $carry;
            },
            0
        );

        return $maxAddition;
    }
}
