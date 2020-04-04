<?Php
//find /mnt/f/opptak/Alaska\ State\ Troopers/2014*.ts -exec php snapshottitle.php {} \;
//find /mnt/ext4/opptak/Phineas\ and\ Ferb/2015*.ts -exec php snapshottitle.php --config phineas_ferb_hd {} \;
//find /mnt/ext4/opptak/24\ timer\ på\ legevakten/2017*.ts -exec php snapshottitle.php --config 24hoursae {} \;
//find "/mnt/ext4/opptak/Milo Murphys lov" -name "2018*ts" -type f -size +10M -exec php snapshottitle.php -config milo {} \;
use datagutten\snapshottitle;
use datagutten\tools\color\color;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Symfony\Component\Filesystem\Filesystem;

require 'vendor/autoload.php';
$filesystem = new Filesystem();
$video=new video;
$image = new snapshottitle\image();

end($argv);
$file=$argv[key($argv)];

echo "Start: ".basename($file)."\n";

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

$xml_config_file = __DIR__.'/snapshottitle_config/'.$options['config'].'.xml';
if(file_exists($xml_config_file)) {
    $xml = simplexml_load_file($xml_config_file, 'SimpleXMLElement'/*, LIBXML_DTDVALID*/);
    if(!empty($xml->{'debug'}->{'scan_start'}))
        $scan_start = (int)$xml->{'debug'}->{'scan_start'};
    if(!empty($xml->{'debug'}->{'scan_end'}))
        $scan_end = (int)$xml->{'debug'}->{'scan_end'};
}

$duration = $video->duration($file);

echo "Duration is $duration\n";

if(!file_exists($folder_snapshots))
{
	$filesystem->mkdir($folder_snapshots);
}
else
	$filesystem->chmod($folder_snapshots, 777);

if(!empty($options['startpos']))
	$pos = $options['startpos'];
elseif(!empty($xml->{'start_position'}))
    $pos = (int)$xml->{'start_position'};
else
	$pos= 1;

printf("Starting at position %d\n", $pos);

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

        $check = color::color_check_xml($im, $xml, $color_debug);

		if($check===false)
		    continue;
        else
        {
            printf("Intro found at frame %d\n", $pos);
        }

		//$inc=2;
		$intropos=$pos;
		$filesystem->copy($imagefile,$folder.'/intro_'.basename($imagefile));
	}
	else //Find title
	{
	    if($pos<$intropos+(int)$xml->{'title_offset'}->{'min'}) //Title is not before X frames after intro
        {
            printf("\nTitle must be least %d seconds from intro, skip frame %d\n", $xml->{'title_offset'}->{'min'}, $pos);
            continue;
        }

        $title_file = sprintf('%s/z%d.png', $folder, $pos);
	    $filesystem->mkdir($folder.'/full');
		$title_file_full = sprintf('%s/full/%d.png', $folder, $pos);

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
            $im2 = $image->crop($im,  $crop['x'], $crop['y'], $crop['w'], $crop['h']);
            imagepng($im2, $title_file);
			$filesystem->copy($imagefile, $title_file_full);
        }
        else {
            printf("\nSaving title frame %d\n", $pos);
            $filesystem->copy($imagefile, $title_file);
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
$filesystem->chmod($folder, 777, 0000, true);