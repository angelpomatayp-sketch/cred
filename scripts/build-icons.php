<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
foreach([192,512] as $size){
 $img=imagecreatetruecolor($size,$size);
 $blue=imagecolorallocate($img,32,71,142);$white=imagecolorallocate($img,255,255,255);$gold=imagecolorallocate($img,240,189,53);
 imagefill($img,0,0,$blue);$s=$size/96;
 imagefilledrectangle($img,(int)(39*$s),(int)(20*$s),(int)(57*$s),(int)(76*$s),$white);
 imagefilledrectangle($img,(int)(20*$s),(int)(39*$s),(int)(76*$s),(int)(57*$s),$white);
 imagefilledellipse($img,(int)(75*$s),(int)(21*$s),(int)(20*$s),(int)(20*$s),$gold);
 imagepng($img,dirname(__DIR__).'/assets/icon-'.$size.'.png');imagedestroy($img);
}
echo "Generated 192px and 512px installation icons.\n";
