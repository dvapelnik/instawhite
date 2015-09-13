<?php
namespace AppBundle\Instagram;

use AppBundle\Utils\Cache\SessionCacheInterface;
use AppBundle\Utils\Cache\SessionCacheTrait;
use ColorThief\ColorThief;

class ImageComparator implements SessionCacheInterface
{
    use SessionCacheTrait;

    public function getImageMainColor($sourceImage, $isRGB = true)
    {
        if (false === ($dominantColor = $this->getFromCache($sourceImage))) {
            $dominantColor = ColorThief::getColor($sourceImage);

            $this->addToCache($sourceImage, $dominantColor);
        }

        return $isRGB
            ? $dominantColor
            : $this->rgb2hex($dominantColor);
    }

    public function rgb2hex($rgb)
    {
        $hex = "#";
        $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);

        return $hex; // returns the hex value including the number sign (#)
    }

    public function getColorDiff($rgb1, $rgb2)
    {
        return
            abs($rgb1[0] - $rgb2[0]) +
            abs($rgb1[1] - $rgb2[1]) +
            abs($rgb1[2] - $rgb2[2]);
    }

    public function hex2rgb($hex)
    {
        $hex = str_replace("#", "", $hex);

        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $rgb = array($r, $g, $b);

        //return implode(",", $rgb); // returns the rgb values separated by commas
        return $rgb; // returns an array with the rgb values
    }
}