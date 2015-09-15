<?php
namespace AppBundle\Instagram\Filler;

interface CollageFillerInterface
{
    function fillPreparedCollage(&$imagesForCollageData, $options = array());
}