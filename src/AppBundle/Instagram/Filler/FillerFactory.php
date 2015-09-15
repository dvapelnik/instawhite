<?php
namespace AppBundle\Instagram\Filler;

class FillerFactory
{
    public function makeFiller($type)
    {
        switch ($type) {
            case'grid' :
                return new GridFiller();
            case 'random':
                return new RandomFiller();
            default:
                throw new \Exception('Filler type not supported');
        }
    }
}