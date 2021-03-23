<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ ."/config.php";

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * GZIPs a file on disk (appending .gz to the name)
 *
 * From http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
 * Based on function by Kioob at:
 * http://www.php.net/manual/en/function.gzwrite.php#34955
 * 
 * @param string $source Path to file that should be compressed
 * @param integer $level GZIP compression level (default: 9)
 * @return string New filename (with .gz appended) if success, or false if operation fails
 */
function gzCompressFile($source, $dest, $level = 9){ 
    $mode = 'wb' . $level; 
    $error = false; 
    if ($fp_out = gzopen($dest, $mode)) { 
        if ($fp_in = fopen($source,'rb')) { 
            while (!feof($fp_in)) 
                gzwrite($fp_out, fread($fp_in, 1024 * 512)); 
            fclose($fp_in); 
        } else {
            $error = true; 
        }
        gzclose($fp_out); 
    } else {
        $error = true; 
    }
    if ($error)
        return false; 
    else
        return $dest; 
} 

/**
* Produce the gz zip of database based on site info as written
* in the config file 
* The destination dir is $TMP_DIR
*/
function backup_db($site_info){
	switch($site_info["DB_TYPE"]){
		case "mysql":
		default:
			//Create DB Connection using mysqli
			$mysqli = new mysqli($site_info["DB_HOST"],$site_info["DB_USER"],$site_info["DB_PASS"],$site_info["DB_NAME"]); 
			$mysqli->select_db($site_info["DB_NAME"]); 
			$mysqli->query("SET NAMES 'utf8'");
			$queryTables  = $mysqli->query('SHOW TABLES'); 
			
			while($row = $queryTables->fetch_row()) 
				$target_tables[] = $row[0]; 
			
			$content="";
			
			foreach($target_tables as $table)
			{
				$result         =   $mysqli->query('SELECT * FROM '.$table);  
				$fields_amount  =   $result->field_count;  
				$rows_num       =   $mysqli->affected_rows;     
				$res            =   $mysqli->query('SHOW CREATE TABLE '.$table); 
				$TableMLine     =   $res->fetch_row();
				$content        = (!isset($content) ?  '' : $content) . "\n\n".$TableMLine[1].";\n\n";

				for ($i = 0, $st_counter = 0; $i < $fields_amount;   $i++, $st_counter=0) 
				{
					while($row = $result->fetch_row())  
					{ //when started (and every after 100 command cycle):
						if ($st_counter%100 == 0 || $st_counter == 0 )  
						{
								$content .= "\nINSERT INTO ".$table." VALUES";
						}
						$content .= "\n(";
						for($j=0; $j<$fields_amount; $j++)  
						{ 
							$row[$j] = str_replace("\n","\\n", addslashes($row[$j]) ); 
							if (isset($row[$j]))
							{
								$content .= '"'.$row[$j].'"' ; 
							}
							else 
							{   
								$content .= '""';
							}     
							if ($j<($fields_amount-1))
							{
									$content.= ',';
							}      
						}
						$content .=")";
						//every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler
						if ( (($st_counter+1)%100==0 && $st_counter!=0) || $st_counter+1==$rows_num) 
						{   
							$content .= ";";
						} 
						else 
						{
							$content .= ",";
						} 
						$st_counter=$st_counter+1;
					}
				} $content .="\n\n\n";
			}
			$backup_name = $site_info["BACKUP_DB_FILE"].".sql";
			file_put_contents($backup_name, $content);
			$backupGz = gzCompressFile($backup_name, $site_info["BACKUP_DB_FILE"]);
			if(!empty($backupGz))
			{
				unlink($backup_name);
				return $backupGz;
			}
			else{
				return NULL;
			}
		break;
	}
	return NULL;
}

/**
* Produce the gz zip of the site home dir based on site info as written
* in the config file 
* The destination dir is $TMP_DIR
*/
function backup_sitedir($site_info){
	$archive = new PharData($site_info["BACKUP_SITE_FILE"]);
	$archive->buildFromDirectory($site_info["HOME_DIR"]); // make path\to\archive\arch1.tar
	$archive->compress(Phar::GZ); // make path\to\archive\arch1.tar.gz
	unlink($site_info["BACKUP_SITE_FILE"]); // deleting path\to\archive\arch1.tar
	return $site_info["BACKUP_SITE_FILE"].".gz";
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient($credPath,$tokenPath)
{
    $client = new Google_Client();
    $client->setApplicationName('WPSite Backup');
    $client->setScopes(Google_Service_Drive::DRIVE);
    $client->setAuthConfig($credPath);
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
	// By default, the 
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

/**
* Uploads to google drive and cleans up the dir
*/

function create_backup($site_info, $credPath, $tokenPath){
	// Get the API client and construct the service object.
	$client = getClient($credPath, $tokenPath);
	$service = new Google_Service_Drive($client);
	//var_dump($site_info);
	$fTup=array();
	$fTup["BACKUP_DB_FILE"] = backup_db($site_info);
	$fTup["BACKUP_SITE_FILE"] = backup_sitedir($site_info);
	//var_dump($fTup);
	
	foreach($fTup as $key => $fileUp){
		if(!empty($fileUp)){
			// Create the file on your Google Drive
			if(!empty($site_info["FOLDER_ID"])){
				$fileMetadata = new Google_Service_Drive_DriveFile(array(
					'name' => basename($fileUp),
					'parents' => array($site_info["FOLDER_ID"])
					));
			}
			else{
				$fileMetadata = new Google_Service_Drive_DriveFile(array('name' => basename($fileUp)));
			}
			$content = file_get_contents($fileUp);
			$mimeType=mime_content_type($fileUp);
			$file = $service->files->create($fileMetadata, array(
			'data' => $content,
			'mimeType' => $mimeType,
			'fields' => 'id'));
			//printf("File ID: %s\n", $file->id);
			//Delete file
			if(!empty($file->id))
				unlink($fileUp);
		}
	}
}



