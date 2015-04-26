# php_wms2tile - easily convert wms service to tiles for slippy map

**LIVE DEMO:**

- ČÚZK: [http://osm.zby.cz/tiles_cuzk.php/17/70788/44404.test](http://osm.zby.cz/tiles_cuzk.php/17/70788/44404.test)
- ÚHUL: [http://osm.zby.cz/tiles_uhul.php/14/8848/5550.test](http://osm.zby.cz/tiles_uhul.php/14/8848/5550.test)


Address for custom background of iD OSM editor:

    http://osm.zby.cz/tiles_cuzk.php/{z}/{x}/{y}.png
    http://osm.zby.cz/tiles_uhul.php/{z}/{x}/{y}.jpg


## Config

First lines of the PHP script itself

    // we log the execution time
    $time_start = microtime(true);
    
    // url of WMS image query ending with bbox=
    $wmsurl = "http://wms.cuzk.cz/wms.asp?...&BBOX=";
    
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

You are supposed to create the MySQL table:

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



Example sizes for ortophoto map:

    z16 = 870 000
    -> jpg 12 GB
    -> png 43 GB
    
    z15 = 200 000
    -> jpg 2.8 GB
    -> png 10.0 GB
    
    z14 = 50 000
    -> jpg 0.7 GB
    -> png 2.5 GB 
    
    z13 = 13 000
    -> jpg 182 MB
    -> png 650 MB


## License and author

(c) [Pavel Zbytovský](http://zby.cz) 2011

Licensed under MIT.
 
