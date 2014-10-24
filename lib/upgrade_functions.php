<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * This file contains global functions connected to upgrading the database
 * and locales between different versions of Yioop!
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
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
        tokenizer, version3 to 4 pushes out stopwordsRemover used for
        summarization. version 6 to 7 adds stemmers for french, english, german.
        version 7 to 8 adds stemmers for russian and spanish
    */
    if(!isset($locale->configure['strings']["view_locale_version10"])) {
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
    $sql = "SELECT ID FROM VERSION";
    for($i = 0; $i < 3; $i++) {
        $result = @$model->db->execute($sql);
        if($result !== false) {
            $row = $model->db->fetchArray($result);
            if((isset($row['ID']) && $row['ID'] >= YIOOP_VERSION) ||
                (isset($row['id']) && $row['id'] >= YIOOP_VERSION)) {
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
    $versions = range(1, YIOOP_VERSION);
    $model = new Model();
    $sql = "SELECT ID FROM VERSION";
    $result = @$model->db->execute($sql);
    if($result !== false) {
        $row = $model->db->fetchArray($result);
        if(isset($row['ID']) && in_array($row['ID'], $versions)) {
            $current_version = $row['ID'];
        } else {
            $current_version = 1;
        }
    } else {
        exit(); // maybe someone else has locked DB, so bail
    }
    $result = NULL; //don't lock db if sqlite
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
        "SESSION VARCHAR(".MAX_GROUP_POST_LEN."))");
}
/**
 * Upgrades a Version 1 version of the Yioop! database to a Version 2 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion2(&$db)
{
    $db->execute("UPDATE VERSION SET ID=2 WHERE ID=1");
    $db->execute("ALTER TABLE USERS ADD UNIQUE ( USER_NAME )" );
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
        NAME VARCHAR(16) PRIMARY KEY, URL VARCHAR(".MAX_URL_LEN.") UNIQUE,
        HAS_QUEUE_SERVER BOOLEAN, NUM_FETCHERS INTEGER)");

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
    $db->execute("ALTER TABLE MACHINE ADD COLUMN PARENT VARCHAR(".NAME_LEN.")");
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
    $db->execute("CREATE TABLE ACTIVE_FETCHER (NAME VARCHAR(".NAME_LEN."),".
        " FETCHER_ID INTEGER)");
    $db->execute("CREATE TABLE CRON_TIME (TIMESTAMP INT(".TIMESTAMP_LEN."))");
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
    if(file_exists($old_archives_path)) {
        rename($old_archives_path, $new_archives_path);
    } else if(!file_exists($new_archives_path)) {
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
    $db->execute("CREATE TABLE USER_GROUP (USER_ID INTEGER ,
        GROUP_ID INTEGER,PRIMARY KEY (GROUP_ID, USER_ID) )");

    addActivityAtId($db, 'db_activity_manage_groups', "manageGroups", 4);
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'en-US',
        'Manage Groups');
    updateTranslationForStringId($db, 'db_activity_manage_groups', 'fr-FR',
        'Modifier les groupes');
    upgradeLocales();
}
/**
 * Upgrades a Version 17 version of the Yioop! database to a Version 18 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion18(&$db)
{
    $dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST,
        "DB_NAME" => DB_NAME, "DB_PASSWORD" => DB_PASSWORD);
    $auto_increment = $db->autoIncrement($dbinfo);
    $db->execute("DELETE FROM VERSION WHERE ID < 17");
    $db->execute("UPDATE VERSION SET ID=18 WHERE ID=17");

    $db->execute("CREATE TABLE ACCESS (NAME VARCHAR(16), ID INTEGER,
                TYPE VARCHAR(16))");
    $db->execute("CREATE TABLE BLOG_DESCRIPTION (TIMESTAMP INT(11) UNIQUE,
                DESCRIPTION VARCHAR(4096))");
    addActivityAtId($db, 'db_activity_blogs_pages', "blogPages", 6);
    updateTranslationForStringId($db, 'db_activity_blogs_pages', 'en-US',
        'Blogs and Pages');
    updateTranslationForStringId($db, 'db_activity_blogs_pages', 'fr-FR',
        'les blogs et les pages');
    upgradeLocales();
}
/**
 * Upgrades a Version 18 version of the Yioop! database to a Version 19 version
 * This update has been superseded by the Version20 update and so its contents
 * have been eliminated except for the change to the version table.
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion19(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 18");
    $db->execute("UPDATE VERSION SET ID=19 WHERE ID=18");
}
/**
 * Upgrades a Version 19 version of the Yioop! database to a Version 20 version
 * This is a major upgrade as the user table have changed. This also acts
 * as a cumulative since version 0.98. It involves a web form that has only
 * been localized to English
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion20(&$db)
{
    if(!isset($_REQUEST['v20step'])) {
        $_REQUEST['v20step'] = 1;
    }
    $upgrade_check_file = WORK_DIRECTORY."/v20check.txt";
    if(!file_exists($upgrade_check_file)) {
        $upgrade_password = substr(sha1(microtime().AUTH_KEY), 0, 8);
        file_put_contents($upgrade_check_file, $upgrade_password);
    } else {
        $v20check = trim(file_get_contents($upgrade_check_file));
        if(isset($_REQUEST['v20step']) && $_REQUEST['v20step'] == 2 &&
            (!isset($_REQUEST['upgrade_code'])||
            $v20check != trim($_REQUEST['upgrade_code']))) {
            $_REQUEST['v20step'] = 1;
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                "v20check.txt not typed in correctly!</h1>')";
        }
    }
    switch($_REQUEST['v20step'])
    {
        case "2":
            /** Get base class for profile_model.php*/
            require_once BASE_DIR."/models/model.php";
            /** For ProfileModel::createDatabaseTables method */
            require_once BASE_DIR."/models/profile_model.php";
            /** For UserModel::addUser method */
            require_once BASE_DIR."/models/user_model.php";
            $profile_model = new ProfileModel(DB_NAME, false);
            $profile_model->db = $db;
            $save_tables = array("ACTIVE_FETCHER", "CURRENT_WEB_INDEX",
                "FEED_ITEM", "MACHINE", "MEDIA_SOURCE", "SUBSEARCH",
                "VERSION");
            $dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST,
                "DB_USER" => DB_USER, "DB_PASSWORD" => DB_PASSWORD,
                "DB_NAME" => DB_NAME);
            $creation_time = microTimestamp();
            $profile = $profile_model->getProfile(WORK_DIRECTORY);
            $new_profile = $profile;
            $new_profile['AUTHENTICATION_MODE'] = NORMAL_AUTHENTICATION;
            $new_profile['FIAT_SHAMIR_MODULUS'] = generateFiatShamirModulus();
            $new_profile['MAIL_SERVER']= "";
            $new_profile['MAIL_PORT']= "";
            $new_profile['MAIL_USERNAME']= "";
            $new_profile['MAIL_PASSWORD']= "";
            $new_profile['MAIL_SECURITY']= "";
            $new_profile['REGISTRATION_TYPE'] = 'disable_registration';
            $new_profile['USE_MAIL_PHP'] = true;
            $new_profile['WORD_SUGGEST'] = true;
            $profile_model->updateProfile(WORK_DIRECTORY, $new_profile,
                $profile);
            //get current users (assume can fit in memory and doesn't take long)
            $users = array();
            $sha1_of_upgrade_code = bchexdec(sha1($v20check));
            $temp = bcpow($sha1_of_upgrade_code . '', '2');
            $zkp_password = bcmod($temp, $new_profile['FIAT_SHAMIR_MODULUS']);
            $user_tables_sql = array("SELECT USER_NAME FROM USER",
                "SELECT USER_NAME, FIRST_NAME, LAST_NAME, EMAIL FROM USERS");
            $i = 0;
            foreach($user_tables_sql as $sql) {
                $result = $db->execute($sql);
                if($result) {
                    while($users[$i] = $db->fetchArray($result)) {
                        $setup_user_fields = array();
                        if($users[$i]["USER_NAME"] == "root" ||
                            $users[$i]["USER_NAME"] == "public") { continue; }
                        $users[$i]["FIRST_NAME"] =
                            (isset($users[$i]["FIRST_NAME"])) ?
                            $users[$i]["FIRST_NAME"] : "FIRST_$i";
                        $users[$i]["LAST_NAME"] =
                            (isset($users[$i]["LAST_NAME"])) ?
                            $users[$i]["LAST_NAME"] : "LAST_$i";
                        $users[$i]["EMAIL"] =
                            (isset($users[$i]["EMAIL"])) ?
                            $users[$i]["EMAIL"] : "user$i@dev.null";
                        /* although not by default using zkp set up so
                           accounts would work on switch
                        */
                        $users[$i]["PASSWORD"] = $v20check;
                        $users[$i]["STATUS"] = INACTIVE_STATUS;
                        $users[$i]["CREATION_TIME"] = $creation_time;
                        $users[$i]["UPS"] = 0;
                        $users[$i]["DOWNS"] = 0;
                        $users[$i]["ZKP_PASSWORD"] = $zkp_password;
                        $i++;
                    }
                    unset($users[$i]);
                    $result = NULL;
                }
            }
            $dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST,
                "DB_USER" => DB_USER, "DB_PASSWORD" => DB_PASSWORD,
                "DB_NAME" => DB_NAME);
            $profile_model->initializeSql($db, $dbinfo);
            $database_tables = array_diff(
                array_keys($profile_model->create_statements),
                $save_tables);
            $database_tables = array_merge($database_tables,
                array("BLOG_DESCRIPTION", "USER_OLD", "ACCESS"));
            foreach($database_tables as $table) {
                if(!in_array($table, $save_tables)){
                    $db->execute("DROP TABLE ".$table);
                }
            }
            if($profile_model->migrateDatabaseIfNecessary(
                $dbinfo, $save_tables)) {
                $user_model = new UserModel(DB_NAME, false);
                $user_model->db = $db;
                foreach($users as $user) {
                    $user_model->addUser($user["USER_NAME"], $user["PASSWORD"],
                        $user["FIRST_NAME"], $user["LAST_NAME"],
                        $user["EMAIL"], $user["STATUS"], $user["ZKP_PASSWORD"]);
                }
                $user = array();
                $user['USER_ID'] = ROOT_ID;
                $user['PASSWORD'] = $v20check;
                $user["ZKP_PASSWORD"] = $zkp_password;
                $user_model->updateUser($user);
                $db->execute("DELETE FROM VERSION WHERE ID < 19");
                $db->execute("UPDATE VERSION SET ID=20 WHERE ID=19");
                return;
            }
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                "Couldn't migrate database tables from defaults!</h1>')";
        case "1":
        default:
            ?>
            <!DOCTYPE html>
            <html lang='en-US'>
            <head>
            <title>Yioop Upgrade Detected</title>
            <meta name="ROBOTS" content="NOINDEX,NOFOLLOW" />
            <meta name="Author" content="Christopher Pollett" />
            <meta charset="utf-8" />
            <?php if(MOBILE) {?>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <?php } ?>
            <link rel="stylesheet" type="text/css"
                 href="<?php e(BASE_URL); ?>/css/search.css" />
            </head>
            <body class="html-ltr <?php if(MOBILE) {e('mobile'); } ?>" >
            <div id="message" ></div>
            <div class='small-margin-current-activity'>
            <h1 class='center green'>Yioop Upgrade Detected!</h1>
            <p>Upgrading to Version 1 of Yioop from an earlier version
            is a major upgrade. The way passwords are stored and the
            organization of the Yioop database has changed. Here is
            what is preserved by this upgrade:</p>
            <ol>
            <li>Existing crawls and archive data.</li>
            <li>Machines known if this instance is a name server.</li>
            <li>Media sources and subsearches.</li>
            <li>Feed items.</li>
            </ol>
            <p>Here is what happens during the upgrade which might
            result in data loss:</p>
            <ol>
            <li>Root and user account passwords are changed to the contents of
            v20check.txt.</li>
            <li>User accounts other than root are marked as inactived,
            so will have tobe activated under Manage Users before that person
            can sign in.</li>
            <li>All roles except Admin and User are deleted. Root
            will be given Admin role, all other users will receive
            User role.</li>
            <li>All existing groups are deleted.</li>
            <li>Existing crawl mixes will be deleted.</li>
            <li>Any customized translations that begin with the prefix db_.
            Other still in use translations will be preserved.</li>
            </ol>
            <p>If given the above you don't want to upgrade, merely replace
            this folder with the contents of your old Yioop instance and
            you should be able to continue to use Yioop as before.</p>
            <p>If you decide to proceed with the upgrade, please back up
            both your existing database and work directory.</p>
            <form method="post" action="?">
            <p><label for="upgrade-code">
            <b>In the field below enter the string found in the file:<br />
            <span class="green"><?php e(WORK_DIRECTORY."/v20check.txt")?></span>
            </b></label></p>
            <input id='upgrade-code' class="extra-wide-field"
                name="upgrade_code" type="text" />
            <input type="hidden" name="v20step" value="2" />
            <button class="button-box" type="submit">Upgrade</button>
            </form>
            <?php
        break;
    }
    ?>
    </div>
    <script type="text/javascript" src="<?php e(BASE_URL);
        ?>/scripts/basic.js" ></script>
    <script type="text/javascript" >
    <?php
    if(isset($data['SCRIPT'])) {
        e($data['SCRIPT']);
    }
    ?></script>
    </body>
    </html>
   <?php
   exit();
}
/**
 * Upgrades a Version 20 version of the Yioop! database to a Version 21 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion21(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 20");
    $db->execute("UPDATE VERSION SET ID=21 WHERE ID=20");
    $db->execute("CREATE TABLE GROUP_THREAD_VIEWS(
        THREAD_ID INTEGER PRIMARY KEY, NUM_VIEWS INTEGER)");
    $db->execute("ALTER TABLE MEDIA_SOURCE RENAME TO MEDIA_SOURCE_OLD");
    $db->execute("CREATE TABLE MEDIA_SOURCE (TIMESTAMP NUMERIC(11) PRIMARY KEY,
        NAME VARCHAR(64) UNIQUE, TYPE VARCHAR(16), SOURCE_URL VARCHAR(256),
        AUX_INFO VARCHAR(512), LANGUAGE VARCHAR(7))");
    DatasourceManager::copyTable("MEDIA_SOURCE_OLD", $db, "MEDIA_SOURCE", $db);
    $db->execute("DROP TABLE MEDIA_SOURCE_OLD");
}
/**
 * Upgrades a Version 21 version of the Yioop! database to a Version 22 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion22(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 21");
    $db->execute("UPDATE VERSION SET ID=22 WHERE ID=21");
    $db->execute("INSERT INTO GROUP_THREAD_VIEWS
        SELECT DISTINCT PARENT_ID, 1 FROM GROUP_ITEM WHERE
        NOT EXISTS (SELECT THREAD_ID
        FROM GROUP_THREAD_VIEWS WHERE THREAD_ID=PARENT_ID)");
    $db->execute("ALTER TABLE LOCALE ADD ACTIVE INTEGER DEFAULT 1");
}
/**
 * Upgrades a Version 22 version of the Yioop! database to a Version 23 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion23(&$db)
{
    $db->execute("DELETE FROM VERSION WHERE ID < 22");
    $db->execute("UPDATE VERSION SET ID=23 WHERE ID=22");
    $db->execute("ALTER TABLE GROUPS ADD POST_LIFETIME INTEGER DEFAULT ".
        FOREVER);
}
/**
 * Upgrades a Version 23 version of the Yioop! database to a Version 24 version
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion24(&$db)
{
    /** Get base class for profile_model.php*/
    require_once BASE_DIR."/models/model.php";
    /** For ProfileModel::createDatabaseTables method */
    require_once BASE_DIR."/models/profile_model.php";
    $db->execute("DELETE FROM VERSION WHERE ID < 23");
    $db->execute("UPDATE VERSION SET ID=24 WHERE ID=23");
    $profile_model = new ProfileModel(DB_NAME, false);
    $profile_model->db = $db;
    $dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_USER" => DB_USER,
        "DB_PASSWORD" => DB_PASSWORD, "DB_NAME" => DB_NAME);
    $profile_model->initializeSql($db, $dbinfo);
    foreach($profile_model->create_statements as $object_name => $statement) {
        if(stristr($object_name, "_INDEX")) {
            if(!$db->execute($statement)) {
                echo $statement." ERROR!";
                exit();
            }
        } else {
            if(!$db->execute("ALTER TABLE $object_name RENAME TO " .
                $object_name . "_OLD")) {
                echo "RENAME $object_name ERROR!";
                exit();
            }
            if(!$db->execute($statement)) {
                echo $statement." ERROR!";
                exit();
            }
            DatasourceManager::copyTable($object_name."_OLD", $db,
                $object_name, $db);
            $db->execute("DROP TABLE ".$object_name."_OLD");
        }
    }
}
/**
 * Upgrades a Version 24 version of the Yioop! database to a Version 25 version
 * This version upgrade includes creation of Help group that holds help pages.
 * Help Group is created with GROUP_ID=HELP_GROUP_ID. If a Group with
 * Group_ID=HELP_GROUP_ID already exists,
 * then that GROUP is moved to the end of the GROUPS table(Max group id is
 * used).
 *
 * @param object $db datasource to use to upgrade
 */
function upgradeDatabaseVersion25(&$db)
{
    /** For reading HELP_GROUP_ID**/
    require_once BASE_DIR . "/configs/config.php";
    /** For GroupModel::setPageName method */
    require_once BASE_DIR . "/models/group_model.php";
    $db->execute("DELETE FROM VERSION WHERE ID < 24");
    $db->execute("UPDATE VERSION SET ID=25 WHERE ID=24");
    $sql = "SELECT COUNT(*) AS NUM FROM GROUPS WHERE GROUP_ID=" . HELP_GROUP_ID;
    $result = $db->execute($sql);
    $row = ($db->fetchArray($result));
    $is_taken = intval($row['NUM']);
    if($is_taken > 0) {
        //Get the max group Id , increment it to push the old group
        $sql = "SELECT MAX(GROUP_ID) AS MAX_GROUP_ID FROM GROUPS";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $max_group_id = $row['MAX_GROUP_ID'] + 1;
        $tables_to_update_group_id = array("GROUPS", "GROUP_ITEM", "GROUP_PAGE",
            "GROUP_PAGE_HISTORY", "USER_GROUP");
        foreach($tables_to_update_group_id as $table) {
            $sql = "UPDATE $table "
                . "Set GROUP_ID=$max_group_id "
                . "WHERE "
                . "GROUP_ID=" . HELP_GROUP_ID;
            $db->execute($sql);
        }
    }
    //Insert the Help Group
    $creation_time = microTimestamp();
    $sql = "INSERT INTO GROUPS VALUES(" . HELP_GROUP_ID . ",'Help','"
        . $creation_time . "','" . ROOT_ID . "','" . PUBLIC_JOIN . "', '"
        . GROUP_READ . "', " . NON_VOTING_GROUP . ", " . FOREVER . ")";
    $db->execute($sql);
    $now = time();
    $db->execute("INSERT INTO USER_GROUP VALUES (" . ROOT_ID . ", " .
        HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
    $db->execute("INSERT INTO USER_GROUP VALUES (" . PUBLIC_USER_ID . ", " .
        HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
    //Insert into Groups
    $help_pages = getWikiHelpPages();
    foreach($help_pages as $page_name => $page_content) {
        $page_content = str_replace("&amp;", "&", $page_content);
        $page_content = @htmlentities($page_content, ENT_QUOTES, "UTF-8");
        $group_model = new GroupModel(DB_NAME, false);
        $group_model->db = $db;
        $group_model->setPageName(ROOT_ID, HELP_GROUP_ID, $page_name,
            $page_content, "en-US", "Creating Default Pages", "$page_name "
            . "Help Page Created!", "Discuss the page in this thread!");
    }
}
/**
 * Reads the Help articles from default db and returns the array of pages.
 */
function getWikiHelpPages()
{
    $help_pages = array();
    require_once(BASE_DIR . "/models/datasources/sqlite3_manager.php");
    $default_dbm = new Sqlite3Manager();
    $default_dbm->connect("", "", "", BASE_DIR . "/data/default.db");
    if(!$default_dbm) {
        return false;
    }
    $group_model = new GroupModel(DB_NAME, true);
    $group_model->db = $default_dbm;
    $page_list = $group_model->getPageList(HELP_GROUP_ID, "en-US", '', 0, 200);
    foreach($page_list[1] as $page) {
        if(isset($page['TITLE'])) {
            $page_info = $group_model->getPageInfoByName(
                HELP_GROUP_ID, $page['TITLE'], "en-US", "api");
            $page_content = str_replace("&amp;", "&", $page_info['PAGE']);
            $page_content = html_entity_decode($page_content, ENT_QUOTES,
                "UTF-8");
            $help_pages[$page['TITLE']] = $page_content;
        }
    }
    return $help_pages;
}
/**
 * Used to insert a new activity into the database at a given acitivity_id
 *
 * Inserting at an ID rather than at the end is useful since activities are
 * displayed in admin panel in order of increasing id.
 *
 * @param resource& $db database handle where Yioop database stored
 * @param string $string_id message identifier to give translations for
 *     for activity
 * @param string  $method_name admin_controller method to be called to perform
 *      this activity
 * @param int $activity_id the id location at which to create this activity
 *     activity at and below this location will be shifted down by 1.
 */
function addActivityAtId(&$db, $string_id, $method_name, $activity_id)
{
    $db->execute("UPDATE ACTIVITY SET ACTIVITY_ID = ACTIVITY_ID + 1 WHERE ".
        "ACTIVITY_ID >= ?", array($activity_id));
    $sql = "SELECT * FROM ACTIVITY WHERE ACTIVITY_ID >= ?
        ORDER BY ACTIVITY_ID DESC";
    $result = $db->execute($sql, array($activity_id));
    while($row = $db->fetchArray($result)) {
        $db->execute("INSERT INTO ACTIVITY VALUES (?, ?, ?)",
            array(($row['ACTIVITY_ID'] + 1), $row['TRANSLATION_ID'],
            $row['METHOD_NAME']));
        $db->execute("DELETE FROM ACTIVITY WHERE ACTIVITY_ID = ?",
            array($row['ACTIVITY_ID']));
    }
    $db->execute("UPDATE ROLE_ACTIVITY SET ACTIVITY_ID = ACTIVITY_ID + 1 " .
        "WHERE ACTIVITY_ID >= ?", array($activity_id));
    //give root account permissions on the activity.
    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (1, ?)",
        array($activity_id));
    $sql = "SELECT COUNT(*) AS NUM FROM TRANSLATION";
    $result = $db->execute($sql);
    if(!$result || !($row = $db->fetchArray($result))) {
        echo "Upgrade activity error";
        exit();
    }
    //some search id start at 1000, so +1001 ensures we steer clear of them
    $translation_id = $row['NUM'] + 1001;
    $db->execute("INSERT INTO ACTIVITY VALUES (?, ?, ?)",
        array($activity_id, $translation_id, $method_name));
    $db->execute("INSERT INTO TRANSLATION VALUES (?, ?)",
        array($translation_id, $string_id));
}
/**
 * Adds or replaces a translation for a database message string for a given
 * IANA locale tag.
 *
 * @param resource& $db database handle where Yioop database stored
 * @param string $string_id message identifier to give translation for
 * @param string $locale_tag  the IANA language tag to update the strings of
 * @param string $translation the translation for $string_id in the language
 *     $locale_tag
 */
function updateTranslationForStringId(&$db, $string_id, $locale_tag,
    $translation)
{
    $sql = "SELECT LOCALE_ID FROM LOCALE ".
        "WHERE LOCALE_TAG = ? " . $db->limitOffset(1);
    $result = $db->execute($sql, array($locale_tag));
    $row = $db->fetchArray($result);
    $locale_id = $row['LOCALE_ID'];

    $sql = "SELECT TRANSLATION_ID FROM TRANSLATION ".
        "WHERE IDENTIFIER_STRING = ? " . $db->limitOffset(1);
    $result = $db->execute($sql, array($string_id));
    $row = $db->fetchArray($result);
    $translate_id = $row['TRANSLATION_ID'];
    $sql = "DELETE FROM TRANSLATION_LOCALE ".
        "WHERE TRANSLATION_ID =? AND ".
        "LOCALE_ID = ?";
    $result = $db->execute($sql, array($translate_id, $locale_id));

    $sql = "INSERT INTO TRANSLATION_LOCALE VALUES (?, ?, ?)";
    $result = $db->execute($sql, array($translate_id, $locale_id,
        $translation));
}
?>