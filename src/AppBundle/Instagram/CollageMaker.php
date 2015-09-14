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

        $scaleRate = $this->getScaleRate($images);
        $borderWidth = ceil(min($this->size, $this->size) / 100);
        $imageSize = 0;

        foreach ($images as &$image) {
            $image->scaleImage(
                ceil($image->getImageWidth() * $scaleRate) - $borderWidth * 2,
                $image->getImageHeight(),
                true
            );
            $imageSize = $image->getImageWidth();
            $image->borderImage(new \ImagickPixel('white'), $borderWidth, $borderWidth);
        }
        unset ($image);

        $countByX = $countByY = sqrt($this->getCountOfCells($images));

        $counter = 0;
        $imagesInCollage = array();

        $yCoordinateCounter = 5;
        for ($iX = 0; $iX < $countByX; $iX++) {
            $xCoordinateCounter = 5;

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
                                    $imagesInCollage,
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

                $imagesInCollage[] = $image;

                $x = $xCoordinateCounter;
                $y = $yCoordinateCounter;

                $image->rotateImage(
                    new \ImagickPixel('none'),
                    rand(-$this->rotateDegreeDelta, $this->rotateDegreeDelta)
                );

                $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER, $x, $y);

                $xCoordinateCounter += $imageSize;

                $counter++;
            }

            $yCoordinateCounter += $imageSize;
        }

        return $canvas;
    }

    /**
     * @param \Imagick[] $images
     *
     * @return float
     */
    private function getScaleRate($images)
    {
        $countOnGrid = $this->getCountOfCells($images);

        $summaryImageArea = $countOnGrid * pow($images[0]->getImageWidth(), 2);

        $canvasArea = pow($this->size, 2);

        return sqrt($canvasArea / $summaryImageArea);
    }

    private function getCountOfCells($images)
    {
        return pow(ceil(sqrt(count($images))), 2);
    }
}