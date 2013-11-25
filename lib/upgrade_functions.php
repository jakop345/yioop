<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * This file contains global functions connected to upgrading the database
 * and locales between different versions of Yioop!
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

 /**
 * Checks to see if the locale data of Yioop! in the work dir is older than the
 * currently running Yioop!
 */
function upgradeLocalesCheck()
{
    global $locale_tag;
    if(!PROFILE) return;
    $config_name = LOCALE_DIR."/$locale_tag/configure.ini";
    $fallback_config_name =
        FALLBACK_LOCALE_DIR."/$locale_tag/configure.ini";
    if(filemtime($fallback_config_name) > filemtime($config_name)) {
        return "locale";
    }
    return false;
}

/**
 * If the locale data of Yioop! in the work directory is older than the
 * currently running Yioop! then this function is called to at least
 * try to copy the new strings into the old profile.
 */
function upgradeLocales()
{
    if(!PROFILE) return;
    $locale = new LocaleModel();
    $locale->initialize(DEFAULT_LOCALE);
    $force_folders = array();
    /* 
        if we're upgrading version2 to 3 we want to make sure stemmer becomes
        tokenizer
    */
    if(isset($locale->configure['strings']["view_locale_version2"])
        || isset($locale->configure['strings']["view_locale_version3"])) {
        $force_folders = array("resources");
    }
    $locale->extractMergeLocales($force_folders);
}

/**
 * Checks to see if the database data or work_dir folder of Yioop! is from an
 * older version of Yioop! than the currently running Yioop!
 */
function upgradeDatabaseWorkDirectoryCheck()
{
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";
    for($i = 0; $i < 3; $i++) {
        $result = @$model->db->execute($sql);
        if($result !== false) {
            $row = $model->db->fetchArray($result);
            if(isset($row['ID']) && $row['ID'] >= 17) {
                return false;
            } else {
                return true;
            }
        }
        sleep(3);
    }
    exit();
}

/**
 * If the database data of Yioop is older than the version of the
 * currently running Yioop then this function is called to try
 * upgrade the database to the new version
 */
function upgradeDatabaseWorkDirectory()
{
    $versions = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 13, 14, 15, 16, 17);
    $model = new Model();
    $model->db->selectDB(DB_NAME);
    $sql = "SELECT ID FROM VERSION";
    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $model->db->fetchArray($result);
        if(isset($row['ID']) && in_array($row['ID'], $versions)) {
            $current_version = $row['ID'];
        } else {
            $current_version = 0;
        }
    } else {
        exit(); // maybe someone else has locked DB, so bail
    }
    $key = array_search($current_version, $versions);
    $versions = array_slice($versions, $current_version);
    foreach($versions as $version) {
        $upgradeDB = "upgradeDatabaseVersion$version";
        $upgradeDB($model->db);
    }
}

/**
 * Upgrades a Version 0 version of the Yioop! database to a Version 1 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion1(&$db)
{
    $db->execute("CREATE TABLE VERSION (ID INTEGER PRIMARY KEY)");
    $db->execute("INSERT INTO VERSION VALUES (1)");
    $db->execute("CREATE TABLE USER_SESSION( USER_ID INTEGER PRIMARY KEY, ".
        "SESSION VARCHAR(4096))");
}

/**
 * Upgrades a Version 1 version of the Yioop! database to a Version 2 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion2(&$db)
{
    $db->execute("UPDATE VERSION SET ID=2 WHERE ID=1");
    $db->execute("ALTER TABLE USER ADD UNIQUE ( USER_NAME )" );
    $db->execute("INSERT INTO LOCALE VALUES (
        17, 'kn', 'ಕನ್ನಡ', 'lr-tb')");
    $db->execute("INSERT INTO LOCALE VALUES (
        18, 'hi', 'हिन्दी', 'lr-tb')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (3, 5,
        'Modifier les rôles')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (4, 5,
        'Modifier les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (5, 5,
        'Mélanger les indexes')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5,
        'Les filtres de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5,
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5,
        'Configurer')");

}

/**
 * Upgrades a Version 2 version of the Yioop! database to a Version 3 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion3(&$db)
{
    $db->execute("UPDATE VERSION SET ID=3 WHERE ID=2");
    $db->execute("INSERT INTO LOCALE VALUES (19, 'tr', 'Türkçe', 'lr-tb')");

    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 10)");

    $db->execute("CREATE TABLE MACHINE (
        NAME VARCHAR(16) PRIMARY KEY, URL VARCHAR(256) UNIQUE,
        HAS_QUEUE_SERVER BOOLEAN, NUM_FETCHERS INT(4))");

    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID>5 AND ACTIVITY_ID<11");
    $db->execute(
        "DELETE FROM TRANSLATION WHERE TRANSLATION_ID>5 AND TRANSLATION_ID<11");
    $db->execute("DELETE FROM TRANSLATION_LOCALE ".
        "WHERE TRANSLATION_ID>5 AND TRANSLATION_ID<11");

    $db->execute("INSERT INTO ACTIVITY VALUES (6, 6, 'pageOptions')");
    $db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'searchFilters')");
    $db->execute("INSERT INTO ACTIVITY VALUES (8, 8, 'manageMachines')");
    $db->execute("INSERT INTO ACTIVITY VALUES (9, 9, 'manageLocales')");
    $db->execute("INSERT INTO ACTIVITY VALUES (10, 10, 'configure')");

    $db->execute(
        "INSERT INTO TRANSLATION VALUES (6, 'db_activity_file_options')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (7,'db_activity_search_filters')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES(8,'db_activity_manage_machines')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (9,'db_activity_manage_locales')");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (10, 'db_activity_configure')");

    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (6, 1, 'Page Options')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Search Filters')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (8, 1, 'Manage Machines')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (9, 1, 'Manage Locales')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (10, 1, 'Configure')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (6, 5,
        'Options de fichier')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5,
        'Les filtres de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5,
        'Modifier les ordinateurs')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 5,
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 5,
        'Configurer')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
        9, 9, 'ローケル管理')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 9, '設定')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (
        9, 10, '로케일 관리')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 10, '구성')");

    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 15,
        'Quản lý miền địa phương')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 15,
        'Sắp xếp hoạt động dựa theo hoạch định')");

}

/**
 * Upgrades a Version 3 version of the Yioop! database to a Version 4 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion4(&$db)
{
    $db->execute("UPDATE VERSION SET ID=4 WHERE ID=3");
    $db->execute("ALTER TABLE MACHINE ADD COLUMN PARENT VARCHAR(16)");
}

/**
 * Upgrades a Version 4 version of the Yioop! database to a Version 5 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion5(&$db)
{
    $db->execute("UPDATE VERSION SET ID=5 WHERE ID=4");

    $static_page_path = LOCALE_DIR."/".DEFAULT_LOCALE."/pages";
    if(!file_exists($static_page_path)) {
        mkdir($static_page_path);
    }
    $default_bot_txt_path = "$static_page_path/bot.thtml";
    $old_bot_txt_path = WORK_DIRECTORY."/bot.txt";
    if(file_exists($old_bot_txt_path) && !file_exists($default_bot_txt_path)){
        rename($old_bot_txt_path, $default_bot_txt_path);
    }
    $db->setWorldPermissionsRecursive($static_page_path);
}

/**
 * Upgrades a Version 5 version of the Yioop! database to a Version 6 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion6(&$db)
{
    $db->execute("UPDATE VERSION SET ID=6 WHERE ID=5");

    if(!file_exists(PREP_DIR)) {
        mkdir(PREP_DIR);
    }
    $db->setWorldPermissionsRecursive(PREP_DIR);
}

/**
 * Upgrades a Version 6 version of the Yioop! database to a Version 7 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion7(&$db)
{
    $db->execute("UPDATE VERSION SET ID=7 WHERE ID=6");
    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID=7");
    $db->execute("INSERT INTO ACTIVITY VALUES (7, 7, 'resultsEditor')");
    $db->execute("DELETE FROM TRANSLATION WHERE TRANSLATION_ID=7");
    $db->execute("DELETE FROM TRANSLATION_LOCALE WHERE TRANSLATION_ID=7");
    $db->execute(
        "INSERT INTO TRANSLATION VALUES (7,'db_activity_results_editor')");
    $db->execute(
        "INSERT INTO TRANSLATION_LOCALE VALUES (7, 1, 'Results Editor')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (7, 5,
        'Éditeur de résultats')");
}

/**
 * Upgrades a Version 7 version of the Yioop! database to a Version 8 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion8(&$db)
{
    $db->execute("UPDATE VERSION SET ID=8 WHERE ID=7");
    $db->execute("INSERT INTO LOCALE VALUES (20, 'fa', 'فارسی', 'rl-tb')");
    $db->execute("CREATE TABLE ACTIVE_FETCHER (NAME VARCHAR(16),".
        " FETCHER_ID INT(4))");
    $db->execute("CREATE TABLE CRON_TIME (TIMESTAMP INT(11))");
    $db->execute("INSERT INTO CRON_TIME VALUES ('".time()."')");

    upgradeLocales();
}

/**
 * Upgrades a Version 8 version of the Yioop! database to a Version 9 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion9(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 8");
    $db->execute("UPDATE VERSION SET ID=9 WHERE ID=8");

    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, 11)");

    $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID >= 8");
    $db->execute("DELETE FROM TRANSLATION WHERE TRANSLATION_ID >= 8");
    $db->execute("DELETE FROM TRANSLATION_LOCALE WHERE TRANSLATION_ID >= 8");
    $db->execute("INSERT INTO ACTIVITY VALUES (8, 8, 'searchSources')");
    $db->execute("INSERT INTO ACTIVITY VALUES (9, 9, 'manageMachines')");
    $db->execute("INSERT INTO ACTIVITY VALUES (10, 10, 'manageLocales')");
    $db->execute("INSERT INTO ACTIVITY VALUES (11, 11, 'configure')");
    $db->execute("INSERT INTO TRANSLATION VALUES(8,
        'db_activity_search_services')");
    $db->execute("INSERT INTO TRANSLATION VALUES(9,
        'db_activity_manage_machines')");
    $db->execute("INSERT INTO TRANSLATION VALUES (10,
        'db_activity_manage_locales')");
    $db->execute("INSERT INTO TRANSLATION VALUES (11,
        'db_activity_configure')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 1,
        'Search Sources')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 1,
        'Manage Machines')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 1,
        'Manage Locales')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 1,
        'Configure')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (8, 5,
        'Sources de recherche')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (9, 5,
        'Modifier les ordinateurs')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 5,
        'Modifier les lieux')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 5,
        'Configurer')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 9,
        'ローケル管理')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 9,
        '設定')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10,
        10, '로케일 관리')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11,
        10, '구성')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (10, 15,
        'Quản lý miền địa phương')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES (11, 15,
        'Sắp xếp hoạt động dựa theo hoạch định')");
}

/**
 * Upgrades a Version 9 version of the Yioop! database to a Version 10 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion10(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 9");
    $db->execute("UPDATE VERSION SET ID=10 WHERE ID=9");

    $db->execute("CREATE TABLE MEDIA_SOURCE (TIMESTAMP INT(11) PRIMARY KEY,
        NAME VARCHAR(16) UNIQUE, TYPE VARCHAR(16),
        SOURCE_URL VARCHAR(256), THUMB_URL VARCHAR(256)
        )");
    $db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634195',
        'YouTube', 'video', 'http://www.youtube.com/watch?v={}&',
        'http://img.youtube.com/vi/{}/2.jpg')");
    $db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634196',
        'MetaCafe', 'video', 'http://www.metacafe.com/watch/{}/',
        'http://www.metacafe.com/thumb/{}.jpg')");
    $db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634197',
        'DailyMotion', 'video', 'http://www.dailymotion.com/video/{}',
        'http://www.dailymotion.com/thumbnail/video/{}')");
}

/**
 * Upgrades a Version 10 version of the Yioop! database to a Version 11 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion11(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 10");
    $db->execute("UPDATE VERSION SET ID=11 WHERE ID=10");
    $db->execute("DROP TABLE CRON_TIME");
    $db->execute("ALTER TABLE ROLE_ACTIVITY ADD CONSTRAINT
        PK_RA PRIMARY KEY(ROLE_ID, ACTIVITY_ID)");
    $db->execute("CREATE TABLE SUBSEARCH (LOCALE_STRING VARCHAR(16) PRIMARY KEY,
        FOLDER_NAME VARCHAR(16), INDEX_IDENTIFIER CHAR(13))");
}

/**
 * Upgrades a Version 11 version of the Yioop! database to a Version 12 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion12(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 11");
    $db->execute("UPDATE VERSION SET ID=12 WHERE ID=11");
    $db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images')");
    $db->execute("INSERT INTO MIX_GROUPS VALUES(2, 0, 1)");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(2, 0, 1, 1,
        'media:image')");
    $db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos')");
    $db->execute("INSERT INTO MIX_GROUPS VALUES(3, 0, 1)");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(3, 0, 1, 1,
        'media:video')");
    $db->execute("INSERT INTO SUBSEARCH VALUES('db_subsearch_images',
        'images','m:2',50)");
    $db->execute("INSERT INTO TRANSLATION VALUES (1002,'db_subsearch_images')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
            (1002, 1, 'Images' )");

    $db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_videos',
        'videos','m:3',10)");
    $db->execute("INSERT INTO TRANSLATION VALUES (1003,'db_subsearch_videos')");
    $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
            (1003, 1, 'Videos' )");
}

/**
 * Upgrades a Version 12 version of the Yioop! database to a Version 13 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion13(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 12");
    $db->execute("UPDATE VERSION SET ID=13 WHERE ID=12");
    $db->execute("CREATE TABLE FEED_ITEM (GUID VARCHAR(11) PRIMARY KEY,
        TITLE VARCHAR(512), LINK VARCHAR(256), DESCRIPTION VARCHAR(4096),
        PUBDATE INT, SOURCE_NAME VARCHAR(16))");
    if(!file_exists(WORK_DIRECTORY."/feeds")) {
        mkdir(WORK_DIRECTORY."/feeds");
    }
    upgradeLocales(); //force locale upgrade
}

/**
 * Upgrades a Version 13 version of the Yioop! database to a Version 14 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion14(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 13");
    $db->execute("UPDATE VERSION SET ID=14 WHERE ID=13");
    $db->execute("ALTER TABLE MEDIA_SOURCE ADD LANGUAGE VARCHAR(7)");
}

/**
 * Upgrades a Version 14 version of the Yioop! database to a Version 15 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion15(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 14");
    $db->execute("UPDATE VERSION SET ID=15 WHERE ID=14");
    $db->execute("DELETE FROM MIX_COMPONENTS WHERE MIX_TIMESTAMP=2
        AND GROUP_ID=0");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(
        2, 0, 1, 1, 'media:image site:doc')");
    $db->execute("DELETE FROM MIX_COMPONENTS WHERE MIX_TIMESTAMP=3
        AND GROUP_ID=0");
    $db->execute("INSERT INTO MIX_COMPONENTS VALUES(
        3, 0, 1, 1, 'media:video site:doc')");
    $db->execute("INSERT INTO LOCALE VALUES (21, 'te',
        'తెలుగు', 'lr-tb')");
    upgradeLocales();
}

/**
 * Upgrades a Version 15 version of the Yioop! database to a Version 16 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion16(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 15");
    $db->execute("UPDATE VERSION SET ID=16 WHERE ID=15");


    addActivityAtId($db, 'db_activity_manage_classifiers',
        "manageClassifiers", 4);
    updateTranslationForStringId($db, 'db_activity_manage_classifiers', 'en-US',
        'Manage Classifiers');
    updateTranslationForStringId($db, 'db_activity_manage_classifiers', 'fr-FR',
        'Manage Classifiers');
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'fr-FR',
        'Classificateurs');

    $old_archives_path = WORK_DIRECTORY."/cache/archives";
    $new_archives_path = WORK_DIRECTORY."/archives";
    if (file_exists($old_archives_path)) {
        rename($old_archives_path, $new_archives_path);
    } else {
        mkdir($new_archives_path);
    }
    $db->setWorldPermissionsRecursive($new_archives_path);

    $new_classifiers_path = WORK_DIRECTORY."/classifiers";
    if (!file_exists($new_classifiers_path)) {
        mkdir($new_classifiers_path);
    }
    $db->setWorldPermissionsRecursive($new_classifiers_path);

    upgradeLocales();
}

/**
 * Upgrades a Version 16 version of the Yioop! database to a Version 17 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion17(&$db)
{
    $dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST, 
        "DB_NAME" => DB_NAME, "DB_PASSWORD" => DB_PASSWORD);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("DELETE FROM VERSION WHERE ID < 16");
    $db->execute("UPDATE VERSION SET ID=17 WHERE ID=16");

    $db->execute("CREATE TABLE GROUPS (GROUP_ID INTEGER PRIMARY KEY
        $auto_increment ,GROUP_NAME VARCHAR(128), CREATED_TIME INT(11),
           CREATOR_ID INT(11))");
    $db->execute("CREATE TABLE USER_GROUPS (USER_ID INTEGER ,
        GROUP_ID INTEGER,PRIMARY KEY (GROUP_ID, USER_ID) )");
    $db->execute("CREATE TABLE GROUP_ROLES (GROUP_ID INTEGER ,
        ROLE_ID INTEGER)");

    addActivityAtId($db, 'db_activity_manage_groups', "manageGroups", 4);
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'en-US',
        'Manage Groups');
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'fr-FR',
        'Modifier les groupes');

    upgradeLocales();
}

/**
 * Used to insert a new activity into the database at a given acitivity_id
 *
 * Inserting at an ID rather than at the end is useful since activities are
 * displayed in admin panel in order of increasing id.
 *
 * @param resource &db database handle where Yioop database stored
 * @param string $string_id message identifier to give translations for
 *      for activity
 * @param string admin_controller method to be called to perform this activity
 * @param int $activity_id the id location at which to create this activity
 *      activity at and below this location will be shifted down by 1.
 */
function addActivityAtId(&$db, $string_id, $method_name, $activity_id)
{
    
    $db->execute("UPDATE ACTIVITY SET ACTIVITY_ID = ACTIVITY_ID + 1 WHERE ".
        "ACTIVITY_ID >= $activity_id");
    $sql = "SELECT * FROM ACTIVITY WHERE ACTIVITY_ID >= $activity_id
        ORDER BY ACTIVITY_ID DESC";
    $result = $db->execute($sql);
    while($row = $db->fetchArray($result)) {
        $db->execute("INSERT INTO ACTIVITY VALUES
            (".($row['ACTIVITY_ID'] + 1).", {$row['TRANSLATION_ID']},
            '{$row['METHOD_NAME']}')");
        $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID =
            {$row['ACTIVITY_ID']}");
    }
    $db->execute("UPDATE ROLE_ACTIVITY SET ACTIVITY_ID = ACTIVITY_ID + 1 " .
        "WHERE ACTIVITY_ID >= $activity_id");
    //give root account permissions on the activity.
    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, $activity_id)");
    $sql = "SELECT COUNT(*) AS NUM FROM TRANSLATION";
    $result = $db->execute($sql);
    if(!$result || !($row = $db->fetchArray($result))) {
        echo "Upgrade activity error";
        exit();
    }
    //some search id start at 1000, so +1001 ensures we steer clear of them
    $translation_id = $row['NUM'] + 1001;
    $db->execute("INSERT INTO ACTIVITY
        VALUES ($activity_id, $translation_id, '$method_name')");
    $db->execute("INSERT INTO TRANSLATION
        VALUES ($translation_id, '$string_id')");
}

/**
 * Adds or replaces a translation for a database message string for a given
 * IANA locale tag.
 *
 * @param resource &db database handle where Yioop database stored
 * @param string $string_id message identifier to give translation for
 * @param string $locale_tag  the IANA language tag to update the strings of
 * @param string $translation the translation for $string_id in the language
 *      $locale_tag
 */
function updateTranslationForStringId(&$db, $string_id, $locale_tag,
    $translation)
{
    $sql = "SELECT LOCALE_ID FROM LOCALE ".
        "WHERE LOCALE_TAG = '$locale_tag' LIMIT 1";
    $result = $db->execute($sql);
    $row = $db->fetchArray($result);
    $locale_id = $row['LOCALE_ID'];

    $sql = "SELECT TRANSLATION_ID FROM TRANSLATION ".
        "WHERE IDENTIFIER_STRING = '$string_id' LIMIT 1";
    $result = $db->execute($sql);
    $row = $db->fetchArray($result);
    $translate_id = $row['TRANSLATION_ID'];
    $sql = "DELETE FROM TRANSLATION_LOCALE ".
        "WHERE TRANSLATION_ID ='$translate_id' AND ".
        "LOCALE_ID = '$locale_id'";
    $result = $db->execute($sql);

    $sql = "INSERT INTO TRANSLATION_LOCALE VALUES ".
        "('$translate_id', '$locale_id', '$translation')";
    $result = $db->execute($sql);
}
?>
