<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * Used to set up the database and filesystem for the
 * seekquarry database system
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage config
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}

/**
 *
 *
 */
define("BASE_DIR", substr($_SERVER['DOCUMENT_ROOT'].
    $_SERVER['PWD'].$_SERVER["SCRIPT_NAME"], 0, 
    -strlen("configs/createdb.php")));
require_once BASE_DIR.'/configs/config.php';
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php"; 
    //get the database library
require_once BASE_DIR."/lib/utility.php"; //for crawlHash function


$db_class = ucfirst(DBMS)."Manager";
$db = new $db_class();
$db->connect();

$auto_increment = "AUTOINCREMENT";
if(in_array(DBMS, array("mysql"))) {
    $auto_increment = "AUTO_INCREMENT";
}
if(in_array(DBMS, array("sqlite"))) {
    $auto_increment = ""; 
    /* in sqlite2 a primary key column will act 
       as auto_increment if don't give value
     */
}
if(!in_array(DBMS, array('sqlite', 'sqlite3'))) {
    $db->execute("DROP DATABASE IF EXISTS ".DB_NAME);
    $db->execute("CREATE DATABASE ".DB_NAME);
} else {
    @unlink(CRAWL_DIR."/data/".DB_NAME.".db");
}
$db->selectDB(DB_NAME);

$db->execute("CREATE TABLE USER( USER_ID INTEGER PRIMARY KEY $auto_increment, ".
    "USER_NAME VARCHAR(16) UNIQUE,  PASSWORD VARCHAR(16))");

//default account is root without a password
$sql ="INSERT INTO USER VALUES (1, 'root', '".crawlCrypt('')."' ) ";
$db->execute($sql);


$db->execute("CREATE TABLE TRANSLATION (TRANSLATION_ID INTEGER PRIMARY KEY ".
    "$auto_increment, IDENTIFIER_STRING VARCHAR(512) UNIQUE)");

$db->execute("CREATE TABLE LOCALE (LOCALE_ID INTEGER PRIMARY KEY ".
    "$auto_increment, LOCALE_TAG VARCHAR(16), LOCALE_NAME VARCHAR(256),".
    " WRITING_MODE CHAR(5))");
$db->execute("CREATE TABLE TRANSLATION_LOCALE (TRANSLATION_ID INTEGER, ".
    "LOCALE_ID INTEGER, TRANSLATION VARCHAR(4096) )");
/* we insert 1 by 1 rather than comma separate as sqlite 
   does not support comma separated inserts
 */
$db->execute("INSERT INTO LOCALE VALUES (1, 'en-US', 'English', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (2, 'fr-FR', 'Français', 'lr-tb')");
$db->execute("INSERT INTO LOCALE VALUES (3, 'vi-VN', 'Tiếng Việt', 'lr-tb')");

$db->execute("CREATE TABLE ROLE (ROLE_ID INTEGER PRIMARY KEY ".
    "$auto_increment, NAME VARCHAR(512))");
$sql ="INSERT INTO ROLE VALUES (1, 'Admin' ) ";
$db->execute($sql);

$db->execute("CREATE TABLE ROLE_ACTIVITY (ROLE_ID INTEGER,ACTIVITY_ID INTEGER)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 1)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 2)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 3)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 4)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 5)");
$db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 6)");

$db->execute(
    "CREATE TABLE ACTIVITY (ACTIVITY_ID INTEGER PRIMARY KEY $auto_increment,".
    " TRANSLATION_ID INTEGER, METHOD_NAME VARCHAR(256))");
$db->execute("INSERT INTO ACTIVITY VALUES (1, 1, 'manageAccount')");
$db->execute("INSERT INTO ACTIVITY VALUES (2, 2, 'manageUsers')");
$db->execute("INSERT INTO ACTIVITY VALUES (3, 3, 'manageRoles')");
$db->execute("INSERT INTO ACTIVITY VALUES (4, 4, 'manageCrawl')");
$db->execute("INSERT INTO ACTIVITY VALUES (5, 5, 'manageLocales')");
$db->execute("INSERT INTO ACTIVITY VALUES (6, 6, 'configure')");

$db->execute("INSERT INTO TRANSLATION VALUES (1,'db_activity_manage_account')");
$db->execute("INSERT INTO TRANSLATION VALUES (2, 'db_activity_manage_users')");
$db->execute("INSERT INTO TRANSLATION VALUES (3, 'db_activity_manage_roles')");
$db->execute("INSERT INTO TRANSLATION VALUES (4, 'db_activity_manage_crawl')");
$db->execute("INSERT INTO TRANSLATION VALUES (5,'db_activity_manage_locales')");
$db->execute("INSERT INTO TRANSLATION VALUES (6, 'db_activity_configure')");

$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (1, 1, 'Manage Account' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (2, 1, 'Manage Users')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 1, 'Manage Roles')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 1, 'Manage Crawl')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 1, 'Manage Locales')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 1, 'Configure')");


$db->execute("CREATE TABLE USER_ROLE (USER_ID INTEGER, ROLE_ID INTEGER)");
$sql ="INSERT INTO USER_ROLE VALUES (1, 1)";
$db->execute($sql);

$db->execute("CREATE TABLE CURRENT_WEB_INDEX (CRAWL_TIME INT(11) )");

$db->disconnect();
if(in_array(DBMS, array('sqlite','sqlite3' ))){
    chmod(CRAWL_DIR."/data/".DB_NAME.".db", 0666);
}
echo "Create DB succeeded\n";


?>
