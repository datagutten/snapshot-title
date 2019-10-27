<?Php
//find /mnt/f/opptak/Alaska\ State\ Troopers/2014*.ts -exec php snapshottitle.php {} \;
//find /mnt/ext4/opptak/Phineas\ and\ Ferb/2015*.ts -exec php snapshottitle.php --config phineas_ferb_hd {} \;
//find /mnt/ext4/opptak/24\ timer\ på\ legevakten/2017*.ts -exec php snapshottitle.php --config 24hoursae {} \;
//find "/mnt/ext4/opptak/Milo Murphys lov" -name "2018*ts" -type f -size +10M -exec php snapshottitle.php -config milo {} \;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Symfony\Component\Filesystem\Filesystem;

require 'vendor/autoload.php';
$filesystem = new Filesystem();
$dependcheck=new dependcheck;
$video=new video;

require 'color_check.php';

end($argv);
$file=$argv[key($argv)];

echo "Start: ".basename($file)."\n";

$color_tools=new color;

date_default_timezone_set('GMT');
$debug=true;

$options = getopt("",array('config:','startpos:', 'noimage', 'multi', 'keep'));
if(empty($options))
    die("Usage: php snapshottitle.php --config=[config file] [video file]\n");

if(!file_exists($file))
	die("File not found: {$file}\n");

$pathinfo_infile=pathinfo($file);
$folder_base=$pathinfo_infile['dirname'];

$folder=$folder_base.'/'."snapshots/".$pathinfo_infile['basename'];

$folder_snapshots=$folder.'/2s';
$folder_crop=$folder.'/crop';



$xml_config_file = __DIR__.'/snapshottitle_config/'.$options['config'].'.xml';
if(file_exists($xml_config_file)) {
    $xml = simplexml_load_file($xml_config_file, 'SimpleXMLElement'/*, LIBXML_DTDVALID*/);
    if(!empty($xml->{'debug'}->{'scan_start'}))
        $scan_start = (int)$xml->{'debug'}->{'scan_start'};
    if(!empty($xml->{'debug'}->{'scan_end'}))
        $scan_end = (int)$xml->{'debug'}->{'scan_end'};
}

$duration = $video->duration($file);
$duration_string = $video->seconds_to_time($duration);

echo "Duration is $duration\n";

if(!file_exists($folder_snapshots))
{
	$filesystem->mkdir($folder_snapshots);
}

if(!isset($options['startpos']))
	$pos=1;
else
	$pos=$options['startpos'];

$cmdlist="";

$ffmpeg = FFMpeg::create();
$video_av = $ffmpeg->open($file);

for($inc=2; $pos<=$duration/2; $pos=$pos+$inc)
{
    if(!empty($scan_start) && $pos<$scan_start)
        continue;

	$imagefile=$folder_snapshots.'/'.str_pad($pos,3,'0',STR_PAD_LEFT).'.png';
    $imagefile = sprintf('%s/%04d.png', $folder_snapshots, $pos);

	//No more pictures
	if(!file_exists($imagefile) && !isset($options['noimage'])) {
        $frame = $video_av->frame(TimeCode::fromSeconds($pos));
        $frame->save($imagefile);
    }

	$im=imagecreatefrompng($imagefile);
	
	if(!isset($intropos)) //Search for intro
	{
		printf("Searching %d\r", $pos);
		$color_debug = false;
		if(!empty($xml) && !empty($xml->{'debug'}))
        {
            if(!empty($xml->{'debug'}->{'frame'}) && $pos===(int)$xml->{'debug'}->{'frame'})
                $color_debug = true;
            else
                $color_debug = false;
        }

		$positions = [];
        foreach ($xml->{'search'}->{'position'} as $position_xml)
        {
            $attributes = $position_xml->attributes();
            $position = [(int)$attributes->{'x'}, (int)$attributes->{'y'}];
            if(!empty($attributes->{'color'}))
                $position['color'] = hexdec($attributes->{'color'});
            $positions[] = $position;
        }

		//$check = color_check($im, $positions, $config['color_ref'], $config['limit_low'], $config['limit_high'], $color_debug);
        $check = color_check($im, $positions, hexdec($xml->{'color'}->{'reference'}), (int)$xml->{'color'}->{'low'}, (int)$xml->{'color'}->{'high'}, $color_debug);

		if($check===false)
		    continue;
        else
        {
            printf("Intro found at frame %d\n", $pos);
        }

		//$inc=2;
		$intropos=$pos;
		if(!file_exists($folder.'/intro/'))
			mkdir($folder.'/intro/');
		copy($imagefile,$folder.'/intro/'.basename($imagefile));
		var_dump($intropos);
		//continue;
	}
	else //Find title
	{
	    if($pos<$intropos+(int)$xml->{'title_offset'}->{'min'}) //Title is not before X frames after intro
        {
            printf("\nTitle must be least %d seconds from intro, skip frame %d\n", $xml->{'title_offset'}->{'min'}, $pos);
            continue;
        }

        $title_file = sprintf('%s/z%d.png', $folder, $pos);

        if(isset($xml->{'crop'})) //Crop images to keep only title
        {
            $crop = [];
            foreach($xml->{'crop'}->attributes() as $attribute=>$value)
            {
                $crop[$attribute] = (int)$value;
            }
            print_r($crop);

            printf("\nSaving cropped title frame %d\n", $pos);
            printf("Cropping frame %d X: %d-%d Y: %d-%d\n", $pos, $crop['x'], $crop['x'] + $crop['w'], $crop['y'], $crop['y'] + $crop['h']);
            //$im2=imagecreatetruecolor($title_w,$title_h);
            $im2 = imagecreatetruecolor($crop['w'], $crop['h']);
            //imagecopy($im2,$im,0,0,$title_x,$title_y,$title_w,$title_h);
            imagecopy($im2, $im, 0, 0, $crop['x'], $crop['y'], $crop['w'], $crop['h']);
            imagepng($im2, $title_file);

        }
        else {
            printf("\nSaving title frame %d\n", $pos);
            copy($imagefile, $title_file);
        }

	}

    if($pos>=$intropos+(int)$xml->{'title_offset'}->{'max'})
    {
        if(!isset($options['multi']))
            break;
        else //Search for new intro
        {
            unset($intropos);
        }
    }
	if(isset($xml))
    {
        $limit_type = $xml->{'limit'}->attributes()->{'type'};
        if($limit_type=='frames' && $pos>$xml->{'limit'} && empty($intropos)) {
            printf("Limit %d reached\n", $xml->{'limit'});
            break;
        }
    }

    if(!empty($scan_end) && $pos>=$scan_end) {
        printf("Stopping scan at %d\n", $pos);
        break;
    }
}
if(isset($intropos) && !isset($options['keep']))
    $filesystem->remove($folder_snapshots);
shell_exec("chmod -R 777 \"$folder\"");