<?php
namespace AppBundle\Instagram\Filler;

class RandomFiller implements CollageFillerInterface
{
    function fillPreparedCollage(&$imagesForCollageData, $options = array())
    {
        $size = $options['size'];
        $imageSize = $options['imageSize'];
        $images = $options['images'];
        $rotateDegreeDelta = $options['rotateDegreeDelta'];

        foreach ($images as $image) {
            $x = rand(0, $size - $imageSize);
            $y = rand(0, $size - $imageSize);

            /** @var \Imagick $image */
            $image->rotateImage(
                new \ImagickPixel('none'),
                rand(-$rotateDegreeDelta, $rotateDegreeDelta)
            );

            $imagesForCollageData[] = array(
                'image' => $image,
                'point' => array($x, $y),
            );
        }

    }
}