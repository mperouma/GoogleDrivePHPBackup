# GoogleDrivePHPBackup
A quite nasty first shot which does the trick. Feel free to improve it !

This piece of PHP code can be used to backup a website (root dir + db) on your google drive
It has several prerequisites wich can also be found inside of the config sample

Don't forget to create your GOOGLE API KEY, to place the credentials.json in the same dir as this script and to run composer update to get all dependancies
NOTICE: On the first call, you will have a command line prompt to enter a code 

**THIS VERSION OF THE SCRIPT DOES NOT CREATE A FOLDER ON GDRIVE.**
If you want to put your backup in a specific folder, use FOLDER_ID attribute. 
If not set, backups will be put at the root of your GDrive

**TO ADD TO CRONTAB**
Example: to run the job every monday at 19:30
```
    30 19 * * 1 php <dir of install>/run.php
```
