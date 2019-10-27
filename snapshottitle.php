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



require 'snapshottitle_config/config_'.$options['config'].'.php';


$duration = $video->duration($file);
$duration_string = $video->seconds_to_time($duration);

echo "Duration is $duration\n";

if(!file_exists($folder_snapshots.'/'))
{
	echo "Creating snapshots for {$pathinfo_infile['basename']}\n";
	mkdir($folder_snapshots.'/',0777,true);

	if(isset($options['multi']))
        $stoptime=$duration;
	elseif(!isset($stoptime))
		$stoptime='00:20:00.000';

	/*shell_exec("ffmpeg -loglevel error -stats -i \"{$file}\" -to $stoptime -f image2 -vf fps=fps=1/2 \"$folder_snapshots/%03d.png\"");
	var_Dump($cmd);
	if(!file_exists($folder_snapshots.'/001.png'))
		die("Snapshot creation failed\n");*/
}
if(!isset($titleoffset))
	die("Title offset missing\n");

if(!isset($options['startpos']))
	$pos=1;
else
	$pos=$options['startpos'];

$cmdlist="";

for($inc=1; $pos<=$duration/2; $pos=$pos+$inc)
{
	$imagefile=$folder_snapshots.'/'.str_pad($pos,3,'0',STR_PAD_LEFT).'.png';
    $imagefile = sprintf('%s/%04d.png', $folder_snapshots, $pos);

	//$timestring=date('H:i:s',$pos-3600);
    if($pos==1)
	    $timestring = $video->seconds_to_time($pos-1);
    else
        $timestring = $video->seconds_to_time(($pos*2)-1);
	//No more pictures
	if(!file_exists($imagefile) && !isset($options['noimage']))
    {
		//No more pictures, get 100 new
		mkdir($tmp_dir="$folder_snapshots/tmp",0777,true);
		var_dump($folder_snapshots);
		
		if(isset($titlerange))
		{
			foreach(range($titlerange[0],$titlerange[1],1) as $pos)
			{
				echo "Range $pos\n";
				symlink($file=$folder_snapshots.'/'.str_pad($pos,3,'0',STR_PAD_LEFT).'.png',
                        $link=$folder.'/z'.str_pad($pos,3,'0',STR_PAD_LEFT).'.png');
				var_dump($link);
			}
			break;
		}

		$pics=100;
		if($pos+$pics>$duration/2)
		    $pics=$duration/2-$pos;

        $cmd="ffmpeg -loglevel error -stats -i \"{$file}\" -ss $timestring.000 -f image2 -vf fps=fps=1/2 -vframes $pics \"$tmp_dir/%04d.png\"";
        printf("Creating %d images from position %s, total duration is %s (%d seconds)\n",
            $pics,
            $timestring,
            $duration_string,
            $duration);
        //var_dump($cmd);
		shell_exec($cmd);

		/*if(!file_exists($folder_snapshots.'/tmp/1.png'))
			die("Snapshot creation failed\n");*/

		for($count=1; $count<=$pics; $count++)
		{
		    $tmp_file=sprintf('%s/tmp/%04d.png', $folder_snapshots, $count);
            //When count is 1 the image is the current position
            $padded_file = sprintf('%s/%04d.png', $folder_snapshots, $pos+$count-1);

			//if(!file_exists("$folder_snapshots/tmp/$count.png"))
            if(!file_exists($tmp_file) && $pos+$count-1<=$duration)
			{
			    throw new Exception(sprintf('File %s not found, snapshot creation failed', $tmp_file));
				//echo "File $count not found\n";
				//break;
			}
            else
                //$padded_file = str_pad($pos+$count-1,3,'0',STR_PAD_LEFT).'.png');
                //$padded_file = sprintf('%s/%04d.png', $folder_snapshots, $pos+$count-1);
                rename($tmp_file, $padded_file);
			    //rename("$folder_snapshots/tmp/$count.png","$folder_snapshots/".str_pad($pos+$count-1,3,'0',STR_PAD_LEFT).'.png');
		}
		rmdir($tmp_dir); //Remove the temporary directory
		//die();

	}
	if(!file_exists($imagefile))
	{
		trigger_error("No image file found: $imagefile",E_USER_WARNING);
		continue;
	}
	
	$pathinfo=pathinfo($imagefile);
	if($pathinfo['extension']!='png')
		continue;

	$im=imagecreatefrompng($imagefile);
	
	if(!isset($intropos)) //Search for intro
	{
		printf("Searching %s\n",$pathinfo['basename']);
		$check = color_check($im, $positions, $config['color_ref'], $config['limit_low'], $config['limit_high']);
		if($check===false)
		    continue;
        else
        {
            printf("Match at frame %d\n", $pos);
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
		if($pos<$intropos+$title_min_offset) //Title is not before X frames after intro
			continue;

        if(!isset($fullsize)) //Crop images to keep only title
        {
            $im2=imagecreatetruecolor($title_w,$title_h);
            imagecopy($im2,$im,0,0,$title_x,$title_y,$title_w,$title_h);
            if(!file_exists($folder_crop))
                mkdir($folder_crop);
            imagepng($im2,$folder_crop.'/'.basename($imagefile));
            copy($folder_crop.'/'.basename($imagefile),$folder.'/z'.basename($imagefile));
        }
        else
            imagepng($im, $folder.'/z'.basename($imagefile));

	}
	if($pos>=$intropos+$titleoffset)
    {
        if(!isset($options['multi']))
            break;
        else //Search for new intro
        {
            unset($intropos);
        }

    }

}
shell_exec("chmod -R 777 \"$folder\"");