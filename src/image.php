<?php


namespace datagutten\snapshottitle;


class image
{
    /**
     * @param resource $im
     * @param $x
     * @param $y
     * @param $width
     * @param $height
     * @return resource
     */
    function crop($im, $x, $y, $width, $height)
    {
        $im2 = imagecreatetruecolor($width, $height);
        imagecopy($im2, $im, 0, 0, $x, $y, $width, $height);
        return $im2;
    }
}