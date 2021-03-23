<?php 
require __DIR__ ."/functions.php";
/** USED TO TEST CONFIGURATION IN COMMAND LINE */

foreach($wp_site as $ind => $site){
	create_backup($site, $credPath, $tokenPath);
}
?>