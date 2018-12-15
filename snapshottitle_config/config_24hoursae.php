<?Php
//if(strpos($argv[1],'HD')===false)
//	die("This template is for HD recordings\n");

$titleoffset=4; //Title is within x frames from intro
$title_min_offset=2; //Title is never earlier than x pictures after intro
$stoptime='00:15:00.000';


$title_w=850;
$title_h=250;
$title_x=575;
$title_y=500;

//$color_min=0xf0f0f0;
//$color_max=0xffffff;
//$positions=array(array(680,570),array(1140,577),array(1338,454));
//$positions=array(array(540,455),array(539,533),array(543,515));
$positions=array(array(990,554),array(1089,527),array(1089,455));
//$titlerange=array(16,23);
//ab 9f 81
//b5 aa 89
//b2 a6 88
//$color_min=0xaa8980;
//$color_max=0xb6b08a;

$config['limit_low']=-10;
$config['limit_high']=20;
$config['color_ref']=0x0981d5;

?>