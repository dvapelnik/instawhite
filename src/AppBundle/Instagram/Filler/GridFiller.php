<?php
namespace AppBundle\Instagram\Filler;

class GridFiller implements CollageFillerInterface
{
    function fillPreparedCollage(&$imagesForCollageData, $options = array())
    {
        $size = $options['size'];
        $countByX = $options['countByX'];
        $countByY = $options['countByY'];
        $images = $options['images'];
        $imageSize = $options['imageSize'];
        $rotateDegreeDelta = $options['rotateDegreeDelta'];

        $counter = 0;
        $yCoordinateCounter = 0;
        for ($iX = 0; $iX < $countByX; $iX++) {
            $xCoordinateCounter = 0;

            for ($iY = 0; $iY < $countByY; $iY++) {
                /** @var $image \Imagick */
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
                    rand(-$rotateDegreeDelta, $rotateDegreeDelta)
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
        unset($image);
    }
}