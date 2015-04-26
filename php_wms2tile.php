<?php
/*
 * php_wms2tile
 * http://github.com/zbycz/php_wms2tile
 *
 * (c) Pavel Zbytovský 2011
 *
 * Licensed under MIT.
 */

// we log the execution time
$time_start = microtime(true);

// url of WMS image query ending with bbox=
$wmsurl = "http://wms.cuzk.cz/wms.asp?service=WMS&VERSION=1.1.1&REQUEST=GetMap&SRS=EPSG:4326&LAYERS=RST_KMD_I,hranice_parcel_i,RST_KN_I,dalsi_p_mapy_i,prehledka_kat_prac,prehledka_kat_uz,prehledka_kraju-linie&FORMAT=image/png&transparent=TRUE&WIDTH=256&HEIGHT=256&BBOX=";

// filetype of WMS - png or jpg --> this one is saved and conversion to .jpg/.png is possible
$filetype = 'png';

// sql table name
$table = "tiles_cuzk";

// page called without parameters
$attribution = "<hr>
	<a href='http://www.cuzk.cz/'>&copy; CUZK.cz</a>
	<br>
	<a href='http://github.com/zbycz/php_wms2tile'>php_wms2tile</a> script by <a href='http://zby.cz/'>zbycz</a> 2011
";

// db login
mysql_connect("localhost", "root", "") or die("Could not connect to db");
mysql_select_db("test") or die("Could not select db");
/*
CREATE TABLE `$table` (
  `z` tinyint(2) unsigned NOT NULL,
  `x` int(10) unsigned NOT NULL,
  `y` int(10) unsigned NOT NULL,
  `hits` int(10) unsigned NOT NULL,
  `hits_month` int(10) unsigned NOT NULL,
  `hits_last_month` int(10) unsigned NOT NULL,
  `downloaded` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `calculated` tinyint(4) NOT NULL,
  `image` blob NOT NULL,
  PRIMARY KEY  (`z`,`x`,`y`)
)
*/


// set the default timezone to use. Available since PHP 5.1
date_default_timezone_set('UTC');


// -------------------------------------------------------------------------- //


//url parsing
$parts = array_reverse(explode("/", $_SERVER["REQUEST_URI"]));
$y = intval($parts[0]);
$x = intval($parts[1]);
$z = intval($parts[2]);
$type = strstr($parts[0], '.');



//no params - display showcase page
if ($x == 0 or $y == 0 or $z == 0)
{
	echo "<a href='$_SERVER[PHP_SELF]/15/17697/11101.test'>test</a>";
	echo "<br><br>";

	$result = mysql_query("SELECT z, count(*) FROM $table GROUP BY z");
	$c = 0;
	$s = 0;
	while ($r = mysql_fetch_row($result))
	{
		echo "z$r[0] $r[1]× $r[2] kB $r[3] MB<br>";
		$c += $r[1];
		$s += $r[3];
	}
	echo "z* {$c}× -------- $s MB<br>";

	echo $attribution;
	exit;
}

//.test - display fetched tile over an openstreetmap one
if (!$type OR $type == '.test')
{
	$bbox = getBBOX($x, $y, $z);
	echo "tile: $x $y $z<br>";
	echo "bbox: $bbox<br>";
	echo "<a href='../../'>homepage</a> <a href='$y.jpg'>jpg</a> <a href='$y.png'>png</a><br>";
	echo "<a href='../../".($z+1)."/".($x*2)."/".($y*2).".test'>zoom in</a> ";
	echo "<a href='../../".($z-1)."/".floor($x/2)."/".floor($y/2).".test'>zoom out</a><br>";
	echo "WMS image overlayed over OSM tile:<br>";
	echo "<img src='$y.$filetype' alt='cached WMS image' style='position:absolute;' onmouseover='this.style.display=\"none\"' width='256' height='256'>";
	echo "<img src='http://tile.openstreetmap.org/$z/$x/$y.png' alt='OpenStreetMap' onmouseout='this.previousSibling.style.display=\"block\"' width='256' height='256'>";
	echo $attribution;
	exit;
}

//if($z < 13){
//	renderTextImage("Please, zoom in.");
//	exit;
//}


//retrieve the $image
$time = 0; //update time
$image = ''; //the content
$result = mysql_query("SELECT * FROM $table WHERE x=$x AND y=$y AND z=$z");

//found cached version - serve it!
if (mysql_num_rows($result))
{
	$row = mysql_fetch_assoc($result);
	$time = strtotime($row['downloaded']);
	$image = $row['image'];
}

//compute zoom 13 from 4 images of zoom 14
elseif (FALSE AND $z == 13)
{
	$result = mysql_query("SELECT * FROM $table WHERE z=$z+1 AND x in ($x*2,$x*2+1) AND y in ($y*2,$y*2+1)");

	if (mysql_num_rows($result) < 4)
		renderTextImage("Zoom in and out to get this tile.");

	$im = imagecreate(256, 256);
	while ($r = mysql_fetch_assoc($result))
	{
		$tile = imagecreatefromstring($r['image']);
		imagecopyresampled($im, $tile, $r['x'] % 2 * 128, $r['y'] % 2 * 128, 0, 0, 128, 128, 256, 256);
	}
	ob_start();
	imagejpeg($im, null, 75);
	$image = ob_get_contents();
	ob_end_clean();
	$time = time();
	mysql_query("INSERT INTO $table (x,y,z,calculated,image) VALUES($x,$y,$z,1,'" . mysql_escape_string($image) . "')");
}

// we dont have this, fetch new image from WMS
else
{
	$image = file_get_contents($wmsurl . getBBOX($x, $y, $z));
	$time = time();
	mysql_query("INSERT INTO $table (x,y,z,image) VALUES($x,$y,$z,'" . mysql_escape_string($image) . "')");
}

// update hit counter
mysql_query("UPDATE $table SET hits=hits+1, hits_month=hits_month+1 WHERE x=$x AND y=$y AND z=$z");

// log time
$time = microtime(true) - $time_start;
file_put_contents("$table.log", date('Y-m-d H:i:s') . " $x $y $z - time: $time\n", FILE_APPEND);


// -------------------------------- OUTPUT ----------------------------------------


// write headers and (convert) the content
header("Cache-Control: public");
header("Pragma: cache");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $time) . " GMT");
header("Expires: " . gmdate("D, d M Y H:i:s", $time + 3600 * 24 * 365) . " GMT");

// header('Cache-Control: max-age=86400, must-revalidate');
// header('Cache-Control: ' . 7 * 24 * 60 * 60 );


if ($type == '.jpg' or $type == '.jpeg')
{
	header("Content-Type: image/jpeg");

	if ($filetype == 'jpg')
	{
		header("Content-Length: " . strlen($image));
		echo $image;
	}

	else // convert
	{
		$im = imagecreatefromstring($image);
		imagejpeg($im, null, 70);
	}
}
elseif ($type == '.png')
{
	header("Content-Type: image/png");

	if ($filetype == 'png')
	{
		header("Content-Length: " . strlen($image));
		echo $image;
	}

	else // convert
	{
		$im = imagecreatefromstring($image);
		imagetruecolortopalette($im, "dither", 256);
		imagepng($im, null);
	}
}
else
{
	renderTextImage("$type not supported.");
}

exit;





// -------------------------------- HELPERS ----------------------------------------


//Convert Z/X/Y to bounding box, @see http://mapki.com/index.php?title=Lat/Lon_To_Tile
function getBBOX($x, $y, $zoom)
{
	$lon = -180.0; // x
	$lonWidth = 360.0; // width 360

	//$lat = -90;  // y
	//$latHeight = 180.0; // height 180
	$lat = -1.0;
	$latHeight = 2.0;

	$tilesAtThisZoom = 1 << ($zoom);
	$lonWidth = 360.0 / $tilesAtThisZoom;
	$lon = -180 + ($x * $lonWidth);
	$latHeight = -2.0 / $tilesAtThisZoom;
	$lat = 1 + ($y * $latHeight);

	// convert lat and latHeight to degrees in a mercator projection
	// note that in fact the coordinates go from
	// about -85 to +85 not -90 to 90!
	$latHeight += $lat;
	$latHeight = (2 * atan(exp(pi() * $latHeight))) - (pi() / 2);
	$latHeight *= (180 / pi());

	$lat = (2 * atan(exp(pi() * $lat))) - (pi() / 2);
	$lat *= (180 / pi());

	$latHeight -= $lat;

	if ($lonWidth < 0)
	{
		$lon = $lon + $lonWidth;
		$lonWidth = -$lonWidth;
	}

	if ($latHeight < 0)
	{
		$lat = $lat + $latHeight;
		$latHeight = -$latHeight;
	}

	return join(',', array($lon, $lat, $lon + $lonWidth, $lat + $latHeight));
}

function renderTextImage($txt)
{
	header("Cache-Control: private");
	header("Pragma: no-cache");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()) . " GMT");
	header("Expires: " . gmdate("D, d M Y H:i:s", time()) . " GMT");

	header("Content-Type: image/png");
	$im = imagecreate(256, 256);
	$bg = imagecolorallocate($im, 255, 255, 255);
	$gr = imagecolorallocate($im, 240, 240, 240);
	$black = imagecolorallocate($im, 0, 0, 0);
	imagestring($im, 2, 5, 5, $txt, $black);
	imageline($im, 0, 0, 255, 0, $gr);
	imageline($im, 0, 0, 0, 255, $gr);
	imagetruecolortopalette($im, "dither", 3);
	imagepng($im, null);
	exit;
}
