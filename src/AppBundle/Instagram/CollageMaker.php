<?php
namespace AppBundle\Instagram;

class CollageMaker
{
    private $width;

    private $height;

    private $images;

    /**
     * CollageMaker constructor.
     *
     * @param $width
     * @param $height
     * @param $images
     */
    public function __construct($width, $height, $images)
    {
        $this->width = $width;
        $this->height = $height;
        $this->images = $images;
    }

    public function makeCollage()
    {
        if (count($this->images) === 0) {
            return false;
        }
    }
}