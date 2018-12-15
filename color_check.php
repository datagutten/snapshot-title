<?php
/**
 * Created by PhpStorm.
 * User: Anders
 * Date: 15.12.2018
 * Time: 16.40
 */
require_once 'tools/color.php';

function color_check($im, $positions, $color_ref, $limit_low=-10, $limit_high=10)
{
    $color_tools = new color();
    foreach($positions as $poskey=>$position)
    {
        $color=imagecolorat($im,$position[0],$position[1]);

        $match=$color_tools->colordiff($color_ref,$color,$limit_low,$limit_high);
        if(isset($debug))
        {
            printf("X: %d Y: %d\n",$position[0],$position[1]);
            print_r($color_tools->diff);
            var_dump($match);
        }
        if($match===false)
            return false;
    }
    //echo "Frame $pos match $poskey ".implode(",",$position).' '.dechex($color)."\n";
    return True;
}

function find_black($im, $width=false, $height=false)
{
    if(empty($width))
        $width=imagesx($im);
    if(empty($height))
        $height=imagesy($im);
    $positions = array(
        array(1,1),
        array($width/2, $height/2),
        array($width, $height)
        );
    return color_check($im, $positions, 0x000000, 0, 20);

}