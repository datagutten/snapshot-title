<?Php
//if(strpos($argv[1],'HD')===false)
//	die("This template is for HD recordings\n");

$titleoffset=18; //Title is within x frames from intro
$title_min_offset=16; //Title is never earlier than x pictures after intro
$stoptime='00:00:35.000';

//Sesong 2
/*$title_w=1110;
$title_h=380;
$title_x=230;
$title_y=110;*/
//Sesong 1
$title_w=1000;
$title_h=150;
$title_x=220;
$title_y=200;

//$color_min=0xf0f0f0;
//$color_max=0xffffff;
//$positions=array(array(680,570),array(1140,577),array(1338,454));
//$positions=array(array(540,455),array(539,533),array(543,515));
$positions=array(array(168,335),array(381,381),array(300,135),array(512,130));
$titlerange=array(16,23);
//ab 9f 81
//b5 aa 89
//b2 a6 88
//$color_min=0xaa8980;
//$color_max=0xb6b08a;

$color_min=0xFADA00;
$color_max=0xFFDF0E;


?>