<?php
namespace AppBundle\Instagram;

class CollageMaker
{
    private $rotateDegreeDelta = 10;

    private $size;

    private $images;

    /**
     * CollageMaker constructor.
     *
     * @param $size
     * @param $images
     */
    public function __construct($size, $images)
    {
        $this->size = $size;
        $this->images = $images;

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
     * @return resource
     */
    public function makeCollage()
    {
        shuffle($this->images);

        $canvas = new \Imagick();
        $canvas->newImage($this->size, $this->size, new \ImagickPixel('black'));
        $canvas->setImageFormat('png');

        $background = new \Imagick($this->images[rand(0, count($this->images) - 1)]['path']);
        $background->scaleImage(max($this->size, $this->size), max($this->size, $this->size), true);
        $background->blurImage(10, 10);
        $background->setColorspace(\Imagick::COLOR_BLACK);

        $canvas->compositeImage($background, \Imagick::COMPOSITE_OVER, 0, 0);

        /** @var \Imagick[] $images */
        $images = array_map(
            function ($imageData) {
                return new \Imagick($imageData['path']);
            },
            $this->images
        );

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
        $counter = 0;
        $yCoordinateCounter = 0;
        for ($iX = 0; $iX < $countByX; $iX++) {
            $xCoordinateCounter = 0;

            for ($iY = 0; $iY < $countByY; $iY++) {
                if (isset($images[$counter])) {
                    $image = $images[$counter];
                } else {
                    // Get images which used on collage only one times at current moment
                    $filteredImagesInCollage = array_values(
                        array_map(
                            function ($item) {
                                return $item['object'];
                            },
                            array_filter(
                                array_reduce(
                                    array_map(
                                        function ($item) {
                                            return $item['image'];
                                        },
                                        $imagesForCollageData
                                    ),
                                    function ($carry, $image) {
                                        $oid = spl_object_hash($image);

                                        if (isset($carry[$oid])) {
                                            $carry[$oid]['counter']++;
                                        } else {
                                            $carry[$oid] = array(
                                                'counter' => 1,
                                                'object'  => $image,
                                            );
                                        }

                                        return $carry;
                                    },
                                    array()
                                ),
                                function ($item) {
                                    return $item['counter'] == 1;
                                }
                            )
                        )
                    );

                    $image = $filteredImagesInCollage[rand(
                        0,
                        count($filteredImagesInCollage) - 2         // Do not use last item
                    )];
                }


                $x = $xCoordinateCounter;
                $y = $yCoordinateCounter;

                $image->rotateImage(
                    new \ImagickPixel('none'),
                    rand(-$this->rotateDegreeDelta, $this->rotateDegreeDelta)
                );

                $xCoordinateCounter += $imageSize;
                $imagesForCollageData[] = array(
                    'image' => $image,
                    'point' => array($x, $y),
                );
                $counter++;
            }

            $yCoordinateCounter += $imageSize;
        }
        //endregion

        $imagesForCollage = array_map(
            function ($imageForCollage) {
                return $imageForCollage['image'];
            },
            $imagesForCollageData
        );

        $widthAddition = $this->getWidthAddition($imagesForCollage, $imageSize);
        $heightAddition = $this->getHeightAddition($imagesForCollage, $imageSize);

        $temporaryCanvas = new \Imagick();
        $temporaryCanvas->newImage(
            $this->size + $widthAddition,
            $this->size + $heightAddition,
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