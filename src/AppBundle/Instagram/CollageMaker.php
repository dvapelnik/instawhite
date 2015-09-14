<?php
namespace AppBundle\Instagram;

class CollageMaker
{
    private $rotateDegreeDelta = 10;

    private $randomizedPoints = array();

    private $width;

    private $height;

    private $images;

    /**
     * CollageMaker constructor.
     *
     * @param $width
     * @param $height
     * @param $images
     * @param $profilePicture
     */
    public function __construct($width, $height, $images)
    {
        $this->width = $width;
        $this->height = $height;
        $this->images = $images;

        $this->check();
    }

    protected function check()
    {
        $message = null;

        if (intval($this->width) != $this->width) {
            $message = "'width' value should be an integer";
        }

        if (intval($this->height) != $this->height) {
            $message = "'height' value should be an integer";
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
        $canvas->newImage($this->width, $this->height, new \ImagickPixel('black'));
        $canvas->setImageFormat('png');

        $background = new \Imagick($this->images[rand(0, count($this->images) - 1)]['path']);
        $background->scaleImage(max($this->width, $this->height), max($this->width, $this->height), true);
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
        $borderWidth = ceil(min($this->width, $this->height) / 100);
        $cellSize = $this->getCellSize($images);

        $xCoordinateCounter = 0;
        $yCoordinateCounter = 0;

        for ($i = 0; $i < $this->getCountOfCells($images); $i++) {
            if (!isset($images[$i])) {
                break;
            }

            $image = $images[$i];

            $image->borderImage(new \ImagickPixel('white'), $borderWidth, $borderWidth);
            $image->rotateImage(new \ImagickPixel('none'), rand(-$this->rotateDegreeDelta, $this->rotateDegreeDelta));
            $image->scaleImage(
                ceil($image->getImageWidth() * $scaleRate),
                $image->getImageHeight(),
                true
            );

            $x = rand(
                $xCoordinateCounter,
                max($cellSize, $image->getImageWidth()) - min($cellSize, $image->getImageWidth())
            );
            $y = rand(
                $yCoordinateCounter,
                max($cellSize, $image->getImageHeight()) - min($cellSize, $image->getImageHeight())
            );

            $canvas->compositeImage($image, \Imagick::COMPOSITE_OVER, $x, $y);

            $xCoordinateCounter += $cellSize;
            if ($xCoordinateCounter > $this->width - $cellSize) {
                $yCoordinateCounter += $cellSize;
            }
        }

        return $canvas;
    }

    /**
     * @param \Imagick[] $images
     * @param array $canvasSize
     *
     * @return float
     */
    protected function getScaleRate($images)
    {
        $summaryImageArea = array_reduce(
            $images,
            function ($carry, $image) {
                /** @var \Imagick $image */
                return $carry + ($image->getImageWidth() * $image->getImageHeight());
            },
            0
        );

        $canvasArea = $this->width * $this->height;

        return sqrt($canvasArea / $summaryImageArea);
    }

    /**
     * @param \Imagick[] $images
     */
    protected function getCellSize($images)
    {
        $imageSizeAverage = $this->getImageSizeAverage($images);
        $maxCountOfImages = $this->getCountOfCells($images);

        $coeff = $maxCountOfImages / count($images);

        return $imageSizeAverage * $coeff;
    }

    /**
     * @param \Imagick[] $images
     *
     * @return float
     */
    protected function getImageSizeAverage($images)
    {
        return array_reduce(
            $images,
            function ($carry, $image) {
                /** @var \Imagick $image */
                return $carry + $image->getImageWidth();
            },
            0
        ) / count($images);
    }

    protected function getCountOfCells($images)
    {
        $imageSizeAverage = $this->getImageSizeAverage($images);
        $countByX = $this->width / $imageSizeAverage;
        $countByY = $this->height / $imageSizeAverage;

        $maxCountOfImages = ceil($countByX * $countByY);

        return $maxCountOfImages;
    }

    protected function getNextRandomizedPoint($minPoint, $maxPoint)
    {
//        if (count($this->randomizedPoints) == 0) {
        $x = rand($minPoint[0], $maxPoint[0]);
        $y = rand($minPoint[1], $maxPoint[1]);
//        } else {
//
//        }

        $point = array($x, $y);

        $this->randomizedPoints[] = $point;

        return $point;
    }
}