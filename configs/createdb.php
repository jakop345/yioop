<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * This script can be used to set up the database and filesystem for the
 * seekquarry database system. The SeekQuarry system is deployed with a
 * minimal sqlite database so this script is not strictly needed.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage configs
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}

/**
 * Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/configs")));
require_once BASE_DIR.'/configs/config.php';

/* get the database library */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/** Get base class for profile_model.php*/
require_once BASE_DIR."/models/model.php";

/** For ProfileModel::createDatabaseTables method*/
require_once BASE_DIR."/models/profile_model.php";

/** For GroupModel::setPageName method*/
require_once BASE_DIR."/models/group_model.php";

/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

$profile_model = new ProfileModel(DB_NAME, false);
$db_class = ucfirst(DBMS)."Manager";

$dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_USER" => DB_USER,
    "DB_PASSWORD" => DB_PASSWORD, "DB_NAME" => DB_NAME);
if(!in_array(DBMS, array('sqlite', 'sqlite3'))) {
    $db = new $db_class();
    $db->connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    /*  postgres doesn't let you drop a database while connected to it so drop
        tables instead first
     */
    foreach($profile_model->database_tables as $table) {
        $db->execute("DROP TABLE ".$table);
    }
    $db->execute("DROP DATABASE IF EXISTS ".DB_NAME);
    $db->execute("CREATE DATABASE ".DB_NAME);
    $db->disconnect();

    $db->connect(); // default connection goes to actual DB
} else {
    @unlink(CRAWL_DIR."/data/".DB_NAME.".db");
    $db = new $db_class();
    $db->connect();
}

if(!$profile_model->createDatabaseTables($db, $dbinfo)) {
    echo "\n\nCouldn't create database tables!!!\n\n";
    exit();
}

$db->execute("INSERT INTO VERSION VALUES (19)");

$creation_time = microTimestamp();

//numerical value of the blank password
$profile = $profile_model->getProfile(WORK_DIRECTORY);
$new_profile = $profile;
$new_profile['FIAT_SHAMIR_MODULUS'] = generateFiatShamirModulus();
$profile_model->updateProfile(WORK_DIRECTORY, $new_profile, $profile);
$sha1_of_blank_string =  bchexdec(sha1(''));
//calculating V  = S ^ 2 mod N
$temp = bcpow($sha1_of_blank_string . '', '2');
$zkp_password = bcmod($temp, $new_profile['FIAT_SHAMIR_MODULUS']);

//default account is root without a password
$sql ="INSERT INTO USERS VALUES (".ROOT_ID.", 'admin', 'admin','root',
        'root@dev.null', '".crawlCrypt('')."', '".ACTIVE_STATUS.
        "', '".crawlCrypt('root'.AUTH_KEY.$creation_time)."','$creation_time',
        0, 0, '$zkp_password')";
$db->execute($sql);
/* public account is an inactive account for used for public permissions
   default account is root without a password
 */
$sql ="INSERT INTO USERS VALUES (".PUBLIC_USER_ID.", 'all', 'all','public',
        'public@dev.null', '".crawlCrypt('')."', '".INACTIVE_STATUS.
        "', '".crawlCrypt('public' . AUTH_KEY . $creation_time)."',
        '$creation_time', 0, 0, '$zkp_password')";
$db->execute($sql);

//default public group with group id 1
$creation_time = microTimestamp();
$sql = "INSERT INTO GROUPS VALUES(".PUBLIC_GROUP_ID.",'Public','".
    $creation_time."','".ROOT_ID."', '".PUBLIC_JOIN."', '".GROUP_READ.
    "', ".NON_VOTING_GROUP.")";
$db->execute($sql);

$now = time();
$db->execute("INSERT INTO ROLE VALUES (".ADMIN_ROLE.", 'Admin' )");
$db->execute("INSERT INTO ROLE VALUES (".USER_ROLE.", 'User' )");
$db->execute("INSERT INTO USER_ROLE VALUES (".ROOT_ID.", ".ADMIN_ROLE.")");
$db->execute("INSERT INTO USER_GROUP VALUES (".ROOT_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (".PUBLIC_USER_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
$public_pages = array();
$public_pages["404"] = <<<EOD
title=Page Not Found
description=The page you requested cannot be found on our server
END_HEAD_VARS
==The page you requested cannot be found.==
EOD;
$public_pages["409"] = <<<EOD
title=Conflict
description=Your request would result in an edit conflict.
END_HEAD_VARS
==Your request would result in an edit conflict, so will not be processed.==
EOD;
$public_pages["captcha_time_out"] = <<<EOD
title=Captcha/Recover Time Out

END_HEAD_VARS
==Account Timeout==

A large number of captcha refreshes or recover password requests
have been made from this IP address. Please wait until
%s to try again.
EOD;
$public_pages["bot"] = <<<EOD
title=Bot

description=Describes the web crawler used with this
web site
END_HEAD_VARS
==My Web Crawler==

Please Describe Your Robot
EOD;
$public_pages["privacy"] = <<<EOD
title=Privacy Policy

description=Describes what information this site collects and retains about
users and how it uses that information
END_HEAD_VARS
==We are concerned with your privacy==
EOD;
$public_pages["register_time_out"] = <<<EOD
title=Create/Recover Account

END_HEAD_VARS

==Account Timeout==

A number of incorrect captcha responses or recover password requests
have been made from this IP address. Please wait until
%s to access this site.
EOD;
$public_pages["suggest_day_exceeded"] = <<<EOD
EOD;
$public_pages["terms"] = <<<EOD
=Terms of Service=

Please write the terms for the services provided by this website.
EOD;
$public_pages["Syntax"] = <<<EOD
=Yioop Wiki Syntax=

Wiki syntax is a lightweight way to markup a text document so that
it can be formatted and drawn nicely by Yioop.
This page briefly describes the wiki syntax supported by Yioop.

==Headings==
In wiki syntax headings of documents and sections are written as follows:

<nowiki>
=Level1=
==Level2==
===Level3===
====Level4====
=====Level5=====
======Level6======
</nowiki>

and would look like:

=Level1=
==Level2==
===Level3===
====Level4====
=====Level5=====
======Level6======

==Paragraphs==
In Yioop two new lines indicates a new paragraph. You can control
the indent of a paragraph by putting colons followed by a space in front of it:

<nowiki>
: some indent

:: a little more

::: even more

:::: that's sorta crazy
</nowiki>

which looks like:

: some indent

:: a little more

::: even more

:::: that's sorta crazy

==Horizontal Rule==
Sometimes it is convenient to separate paragraphs or sections with a horizontal rule.
This can be done by placing four hyphens on a line by themselves:
<nowiki>
----
</nowiki>
This results in a line that looks like:
----

==Text Formatting Within Paragraphs==
Within a paragraph it is often convenient to make some text bold, italics, underlined, etc. Below is
a quick summary of how to do this:
===Wiki Markup===
{|
|<nowiki>''italic''</nowiki>|''italic''
|-
|<nowiki>'''bold'''</nowiki>|'''bold'''
|-
|<nowiki>'''''bold and italic'''''</nowiki>|'''''bold and italic'''''
|}

===HTML Tags===
Yioop also supports several html tags such as:
{|
|<nowiki><del>delete</del></nowiki>|<del>delete</del>
|-
|<nowiki><ins>insert</ins></nowiki>|<ins>insert</ins>
|-
|<nowiki><s>strike through</s> or
<strike>strike through</strike> </nowiki>|<s>strike through</s>
|-
|<nowiki><sup>superscript</sup> and
<sub>subscript</sub></nowiki>|<sup>superscript</sup> and
<sub>subscript</sub>
|-
|<nowiki><tt>typewriter</tt></nowiki>|<tt>typewriter</tt>
|-
|<nowiki><u>underline</u></nowiki>|<u>underline</u>
|}

===Spacing within Paragraphs===
The HTML entity
<nowiki> </nowiki>
can be used to create a non-breaking space. The tag
<nowiki><br></nowiki>
can be used to produce a line break.
==Preformatted Text==
You can force text to be formatted as you typed it rather
than using the layout mechanism of the browser using the
<nowiki><pre>preformatted text tag.</pre></nowiki>
Alternatively, a sequence of lines all beginning with a
space character will also be treated as preformatted.

==Lists==
The Yioop Wiki Syntax supported of ways of listing items:
bulleted/unordered list, numbered/ordered lists, and
definition lists. Below are some examples:

===Unordered Lists===
<nowiki>
* Item1
** SubItem1
** SubItem2
*** SubSubItem1
* Item 2
* Item 3
</nowiki>
would be drawn as:
* Item1
** SubItem1
** SubItem2
*** SubSubItem1
* Item 2
* Item 3

===Ordered Lists===
<nowiki>
# Item1
## SubItem1
## SubItem2
### SubSubItem1
# Item 2
# Item 3
</nowiki>
# Item1
## SubItem1
## SubItem2
### SubSubItem1
# Item 2
# Item 3

===Mixed Lists===
<nowiki>
# Item1
#* SubItem1
#* SubItem2
#*# SubSubItem1
# Item 2
# Item 3
</nowiki>
# Item1
#* SubItem1
#* SubItem2
#*# SubSubItem1
# Item 2
# Item 3

===Definition Lists===
<nowiki>
;Term 1: Definition of Term 1
;Term 2: Definition of Term 2
</nowiki>
;Term 1: Definition of Term 1
;Term 2: Definition of Term 2

==Tables==
A table begins with {`|`  and ends with `|`}. Cells are separated with | and
rows are separatec with |- as can be seen in the following
example:
<nowiki>
{|
|a||b
|-
|c||d
|}
</nowiki>
{|
|a||b
|-
|c||d
|}
Headings for columns and rows can be made by using an exclamation point, !, rather than
a vertical bar |. For example,
<nowiki>
{|
!a!!b
|-
|c|d
|}
</nowiki>
{|
!a!!b
|-
|c|d
|}
Captions can be added using the + symbol:
<nowiki>
{|
|+ My Caption
!a!!b
|-
|c|d
|}
</nowiki>
{|
|+ My Caption
!a!!b
|-
|c|d
|}
Finally, you can put a CSS class or style attributes (or both) on the first line
of the table to further control how it looks:
<nowiki>
{| class="wikitable"
|+ My Caption
!a!!b
|-
|c|d
|}
</nowiki>
{| class="wikitable"
|+ My Caption
!a!!b
|-
|c|d
|}
Within a cell attributes like align, valign, styles, and class can be used. For example,
<nowiki>
{|
| style="text-align:right;"| a| b
|-
| lalala | lalala
|}
</nowiki>
{|
| style="text-align:right;"| a| b
|-
| lalala | lalala
|}

==Math==

Math can be included into a wiki document by either using the math tag:
<nowiki>
<math>
\sum_{i=1}^{n} i = frac{(n+1)(n)}{2}
</math>
</nowiki>

<math>
\sum_{i=1}^{n} i = frac{(n+1)(n)}{2}
</math>
EOD;
$group_model = new GroupModel(DB_NAME, false);
$group_model->db = $db;
foreach($public_pages as $page_name => $page_content) {
    $group_model->setPageName(ROOT_ID, PUBLIC_USER_ID, $page_name,
        $page_content, "en-US", "Creating Default Pages",
        "$page_name Wiki Page Created!", "Discuss the page in this thread!");
}

/* we insert 1 by 1 rather than comma separate as sqlite
   does not support comma separated inserts
 */
$locales = array(
    array('en-US', 'English', 'lr-tb'),
    array('ar', 'العربية', 'rl-tb'),
    array('de', 'Deutsch', 'lr-tb'),
    array('es', 'Español', 'lr-tb'),
    array('fr-FR', 'Français', 'lr-tb'),
    array('he', 'עברית', 'rl-tb'),
    array('in-ID', 'Bahasa', 'lr-tb'),
    array('it', 'Italiano', 'lr-tb'),
    array('ja', '日本語', 'lr-tb'),
    array('ko', '한국어', 'lr-tb'),
    array('pl', 'Polski', 'lr-tb'),
    array('pt', 'Português', 'lr-tb'),
    array('ru', 'Русский', 'lr-tb'),
    array('th', 'ไทย', 'lr-tb'),
    array('vi-VN', 'Tiếng Việt', 'lr-tb'),
    array('zh-CN', '中文', 'lr-tb'),
    array('kn', 'ಕನ್ನಡ', 'lr-tb'),
    array('hi', 'हिन्दी', 'lr-tb'),
    array('tr', 'Türkçe', 'lr-tb'),
    array('fa', 'فارسی', 'rl-tb'),
    array('te', 'తెలుగు', 'lr-tb'),
);

$i = 1;
foreach($locales as $locale) {
    $db->execute("INSERT INTO LOCALE VALUES ($i, '{$locale[0]}',
        '{$locale[1]}', '{$locale[2]}')");
    $locale_index[$locale[0]] = $i;
    $i++;
}

$activities = array(
    "manageAccount" => array('db_activity_manage_account',
        array(
            "en-US" => 'Manage Account',
            "fa" => 'مدیریت حساب',
            "fr-FR" => 'Modifier votre compte',
            "ja" => 'アカウント管理',
            "ko" => '사용자 계정 관리',
            "vi-VN" => 'Quản lý tài khoản',
            "zh-CN" => '管理帳號',
        )),
    "manageUsers" => array('db_activity_manage_users',
        array(
            "en-US" => 'Manage Users',
            "fa" => 'مدیریت کاربران',
            "fr-FR" => 'Modifier les utilisateurs',
            "ja" => 'ユーザー管理',
            "ko" => '사용자 관리',
            "vi-VN" => 'Quản lý tên sử dụng',
            "zh-CN" => '管理使用者',
        )),
    "manageRoles" => array('db_activity_manage_roles',
        array(
            "en-US" => 'Manage Roles',
            "fa" => 'مدیریت نقش‌ها',
            "fr-FR" => 'Modifier les rôles',
            "ja" => '役割管理',
            "ko" => '사용자 권한 관리',
            "vi-VN" => 'Quản lý chức vụ',
        )),
    "manageGroups" => array('db_activity_manage_groups',
        array(
            "en-US" => 'Manage Groups',
            "fr-FR" => 'Modifier les groupes',
        )),
    "manageCrawls" => array('db_activity_manage_crawl',
        array(
            "en-US" => 'Manage Crawl',
            "fa" => 'مدیریت خزش‌ها',
            "fr-FR" => 'Modifier les indexes',
            "ja" => '検索管理',
            "ko" => '크롤 관리',
            "vi-VN" => 'Quản lý sự bò',
        )),
    "groupFeeds" => array('db_activity_group_feeds',
        array(
            "en-US" => 'My Group Feeds',
        )),
    "mixCrawls" => array('db_activity_mix_crawls',
        array(
            "en-US" => 'Mix Crawls',
            "fa" => 'ترکیب‌های خزش‌ها',
            "fr-FR" => 'Mélanger les indexes',
        )),
    "manageClassifiers" => array('db_activity_manage_classifiers',
        array(
            "en-US" => 'Manage Classifiers',
            "fa" => '',
            "fr-FR" => 'Classificateurs',
        )),
    "pageOptions" => array('db_activity_file_options',
        array(
            "en-US" => 'Page Options',
            "fa" => 'تنظیمات صفحه',
            "fr-FR" => 'Options de fichier',
        )),
    "resultsEditor" => array('db_activity_results_editor',
        array(
            "en-US" => 'Results Editor',
            "fa" => 'ویرایشگر نتایج',
            "fr-FR" => 'Éditeur de résultats',
        )),
    "searchSources" => array('db_activity_search_services',
        array(
            "en-US" => 'Search Sources',
            "fa" => 'منابع جستجو',
            "fr-FR" => 'Sources de recherche',
        )),
    "manageMachines" => array('db_activity_manage_machines',
        array(
            "en-US" => 'Manage Machines',
            "fa" => 'مدیریت دستگاه‌ها',
            "fr-FR" => 'Modifier les ordinateurs',
        )),
    "manageLocales" => array('db_activity_manage_locales',
        array(
            "en-US" => 'Manage Locales',
            "fa" => 'مدیریت زبان‌ها',
            "fr-FR" => 'Modifier les lieux',
            "ja" => 'ローケル管理',
            "ko" => '로케일 관리',
            "vi-VN" => 'Quản lý miền địa phương',
        )),
    "serverSettings" => array('db_activity_server_settings',
        array(
            "en-US" => 'Server Settings',
            "fr-FR" => 'Serveurs',
        )),
    "captchaSettings" => array('db_activity_captcha_settings',
        array(
            "en-US" => 'Captcha Settings',
            "fr-FR" => 'Paramètres Captcha',
        )),

    "configure" => array('db_activity_configure',
        array(
            "en-US" => 'Configure',
            "fa" => 'پیکربندی',
            "fr-FR" => 'Configurer',
            "ja" => '設定',
            "ko" => '구성',
            "vi-VN" => 'Sắp xếp hoạt động dựa theo hoạch định',
        ))

);

$captcha_questions_and_choices = array(
    // Captcha-Most Questions

    $question_choice_most_pair01 = array(
       'question' => array('identifier_string' =>'db_most_question01',
            'method_name' => 'captcha_question_most01',
            'values' => array('en-US' => "Which lives or lasts the longest?",
                'fr-FR' => "Qui vit ou dure le plus longtemps?"
                    )),
       'choices' => array('identifier_string' => 'db_most_captcha_choices01',
            'method_name' => 'captcha_choices_most01',
            'values' => array('en-US' => "lightning, bacteria, ant, dog, ". 
                "horse, person, oak tree, planet, star, galaxy",
                'fr-FR' => "la foudre, les bactéries, une fourmi, un chien, ".
                 "un cheval, une personne, un chêne, une planète, ".
                 "une étoile, une galaxie"
                    ))

    ),
    $question_choice_most_pair02 = array(
       'question' => array('identifier_string' => 'db_most_question02',
            'method_name' => 'captcha_question_most02',
            'values' => array('en-US' => "Which is more abundant?",
                'fr-FR' => "Quel est le plus abondant?",
                    )),

       'choices' => array('identifier_string' => 'db_most_captcha_choices02',
            'method_name' => 'captcha_choices_most02',
            'values' => array('en-US' => "continents, countries, subways, ".
                 "cities, doctors, people, hands, teeth, hair, cells, ".
                 "molecules, atoms, protons",
                'fr-FR' => "continents, les pays, les métros, les villes, ".
                 "les médecins, les gens, les mains, les dents, les cheveux, ".
                 "les cellules, les molécules, les atomes, les protons",
                    ))
    ),


    $question_choice_most_pair03 = array(
       'question' => array('identifier_string' => 'db_most_question03',
            'method_name' => 'captcha_question_most03',
            'values' => array('en-US' => "Which is usually more pricey?",
                'fr-FR' => "Quel est généralement plus cher?",
                    )),

       'choices' => array('identifier_string' => 'db_most_captcha_choices03',
            'method_name' => 'captcha_choices_most03',
            'values' => array('en-US' => "a postage stamp, cup of coffee, ".
                 "movie ticket, shoes, bicycle, one month's rent, car, ".
                 "house, a power plant",
                'fr-FR' => "un timbre-poste, tasse de café, ". 
                 "billet de cinéma, chaussures, vélos, ". 
                 "la location d'un mois, voiture, maison, ".
                 "d'une centrale électrique",
                    ))
    ),

    $question_choice_most_pair04 = array(
       'question' => array('identifier_string' => 'db_most_question04',
            'method_name' => 'captcha_question_most04',
            'values' => array('en-US' => "Which is more round?",
                'fr-FR' => "Quel est le plus rond?",
                    )),

       'choices' => array('identifier_string' => 'db_most_captcha_choices04',
            'method_name' => 'captcha_choices_most04',
            'values' => array('en-US' => "a chair, the letter T, a banana, ".
                 "a pear, an apple, an orange, a sphere",
                'fr-FR' => "une chaise, la lettre T, une banane, une poire, ".
                 "une pomme, une orange, une sphère",
                    ))
    ),

    $question_choice_most_pair05 = array(
       'question' => array('identifier_string' => 'db_most_question05',
            'method_name' => 'captcha_question_most05',
            'values' => array('en-US' => "Which is usually the largest?",
                   'fr-FR' => "Quel est généralement le plus grand?",
                       )),
       'choices' => array('identifier_string' => 'db_most_captcha_choices05',
            'method_name' => 'captcha_choices_most05',
               'values' => array('en-US' => "a coin, a baseball, ". 
                    "a milk gallon, shopping cart, refrigerator, ". 
                    "giraffe, house, volcano, the Moon, the Earth",
                'fr-FR' => "une pièce de monnaie, une balle de baseball, ".
                 "un gallon de lait, panier, réfrigérateur, girafe, maison, ".
                 "volcan, la Lune, la Terre",
                       ))
    ),

    $question_choice_most_pair06 = array(
       'question' => array('identifier_string' => 'db_most_question06',
            'method_name' => 'captcha_question_most06',
            'values' => array('en-US' => "Which is taller?",
                  'fr-FR' => "Quel est le plus grand?",
                      )),
       'choices' => array('identifier_string' => 'db_most_captcha_choices06',
            'method_name' => 'captcha_choices_most06',
               'values' => array('en-US' => "ladybug, mouse, cat, toddler, ". 
                    "man, horse, elephant, giraffe, tall building, mountain",
                'fr-FR' => "coccinelle, souris, chat, enfant, homme, ".
                 "cheval, éléphant, girafe, grand bâtiment, montagne",
                       ))
    ),

    $question_choice_most_pair07 = array(
       'question' => array('identifier_string' => 'db_most_question07',
            'method_name' => 'captcha_question_most07',
            'values' => array('en-US' => "Which takes longer?",
                 'fr-FR' => "Qui prend plus de temps?",
                     )),
       'choices' => array('identifier_string' => 'db_most_captcha_choices07',
            'method_name' => 'captcha_choices_most07',
               'values' => array('en-US' => "blink, lick envelope, ". 
                    "comb hair, apply makeup, watch a movie, run marathon, ". 
                    "olympics, summer vacation, year",
                'fr-FR' => "clignotement, enveloppe lécher, ".
                 "peigner les cheveux, appliquer le maquillage, ".
                 "regarder un film, exécuter marathon, jeux olympiques, ".
                 "les vacances d'été, l'année",
                        ))
    ),

    $question_choice_most_pair08 = array(
       'question' => array('identifier_string' => 'db_most_question08',
            'method_name' => 'captcha_question_most08',
            'values' => array('en-US' => "Which is hotter sounding?",
                 'fr-FR' => "Quel sondage plus chaud?",
                     )),
       'choices' => array('identifier_string' => 'db_most_captcha_choices08',
            'method_name' => 'captcha_choices_most08',
               'values' => array('en-US' => "Pluto, polar exploring, ". 
                    "skating, swimming pool, tea, steam, molten iron, sun",
                'fr-FR' => "Pluton, exploration polaire, patinage, piscine, ".
                 "thé, vapeur, fer fondu, le soleil",
                       ))
    ),

    $question_choice_most_pair09 = array(
       'question' => array('identifier_string' => 'db_most_question09',
            'method_name' => 'captcha_question_most09',
            'values' => array('en-US' => "Which is the oldest?",
                 'fr-FR' => "Quel est la plus ancienne?",
                     )),
       'choices' => array('identifier_string' => 'db_most_captcha_choices09',
            'method_name' => 'captcha_choices_most09',
               'values' => array('en-US' => "fresh milk, current president, ".
                    "your grandparents, Flight at Kitty Hawk, ". 
                    "the Black Death, Egyptian Pyramids, writing, ".
                    "cave paintings, dinosaurs",
                'fr-FR' => "lait frais, actuel président, ".
                 "vos grands-parents, vol à Kitty Hawk, la peste noire, ".
                 "pyramides égyptiennes,  l'écriture, ". 
                 "les peintures rupestres, des dinosaures",
                       ))
    ),

    $question_choice_most_pair10 = array(
       'question' => array('identifier_string' => 'db_most_question10',
            'method_name' => 'captcha_question_most10',
            'values' => array('en-US'=> "Which holds more?",
                'fr-FR'=> "Qui détient plus?",
                    )),
       'choices' => array('identifier_string' => 'db_most_captcha_choices10',
            'method_name' => 'captcha_choices_most10',
               'values' => array('en-US'=> "teaspoon, saucer, cup, bowl, ". 
                    "teapot, wash basin, barrel, pickup truck, ". 
                    "moving van, oil tanker",
                'fr-FR'=> "cuillère à café, soucoupe, tasse, bol, théière, ".
                 "lavabo, baril, camionnette, camion de déménagement, ".
                  "pétrolier",
                       ))
        ),

    //Captcha-Least Questions

    $question_choice_least_pair01 = array(
       'question' => array('identifier_string' => 'db_least_question01',
            'method_name' => 'captcha_question_least01',
               'values' => array('en-US' => "Which lives or lasts the ". 
                   "shortest?",
                'fr-FR' => "Qui vit ou dure le plus court?",
                       )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices01',
            'method_name' => 'captcha_choices_least01',
            'values' => array('en-US' => "lightning, bacteria, ant, dog, ". 
                 "horse, person, oak tree, planet, star, galaxy",
                'fr-FR' => "la foudre, les bactéries, une fourmi, un chien, ".
                  "un cheval, une personne, un chêne, une planète, ". 
                  "une étoile, une galaxie",
                      ))
    ),

    $question_choice_least_pair02 = array(
       'question' => array('identifier_string' => 'db_least_question02',
            'method_name' => 'captcha_question_least02',
               'values' => array('en-US' => "Which is less abundant?",
                'fr-FR' => "Quel est moins abondante?",
                       )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices02',
            'method_name' => 'captcha_choices_least02',
            'values' => array('en-US' => "continents, countries, subways, ".
                 "cities, doctors, people, hands, teeth, hair, cells, ".
                 "molecules, atoms, protons",
                'fr-FR' => "continents, les pays, les métros, les villes, ".
                 "les médecins, les gens, les mains, les dents, ". 
                 "les cheveux, les cellules, les molécules, ". 
                 "les atomes, les protons",
                      ))
    ),

    $question_choice_least_pair03 = array(
       'question' => array('identifier_string' => 'db_least_question03',
            'method_name' => 'captcha_question_least03',
               'values' => array('en-US' => "Which is usually less pricey?",
                'fr-FR' => "Quel est généralement moins cher?",
                       )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices03',
            'method_name' => 'captcha_choices_least03',
             'values' => array('en-US' => "a postage stamp, cup of coffee, ".
                 "movie ticket, shoes, bicycle, one month's rent, car, ".
                 "house, a power plant",
                'fr-FR' => "un timbre-poste, tasse de café, ".
                 "billet de cinéma, chaussures, vélos, ". 
                 "la location d'un mois, voiture, maison, ".
                 "d'une centrale électrique",
                      ))
    ),

    $question_choice_least_pair04 = array(
       'question' => array('identifier_string' => 'db_least_question04',
            'method_name' => 'captcha_question_least04',
               'values' => array('en-US' => "Which is less round?",
                'fr-FR' => "Quel est moins rond?",
                       )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices04',
            'method_name' => 'captcha_choices_least04',
            'values' => array('en-US' => "a chair, the letter T, a banana, ".
                 "a pear, an apple, an orange, a sphere",
                'fr-FR' => "une chaise, la lettre T, une banane, une poire, ".
                 "une pomme, une orange, une sphère",
                      ))
    ),

    $question_choice_least_pair05 = array(
       'question' => array('identifier_string' => 'db_least_question05',
            'method_name' => 'captcha_question_least05',
               'values' => array('en-US' => "Which is usually the smallest?",
                'fr-FR' => "Quel est généralement le plus petit?",
                       )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices05',
            'method_name' => 'captcha_choices_least05',
            'values' => array('en-US' => "a coin, a baseball, ". 
                 "a milk gallon, shopping cart, refrigerator, ". 
                 "giraffe,house, volcano, the Moon, the Earth",
                'fr-FR' => "une pièce de monnaie, une balle de baseball, ".
                 "un gallon de lait, panier, réfrigérateur, girafe, ".
                 "maison, volcan, la Lune, la Terre",
                      ))
    ),

    $question_choice_least_pair06 = array(
       'question' => array('identifier_string' => 'db_least_question06',
            'method_name' => 'captcha_question_least06',
            'values' => array('en-US' => "Which is shorter?",
                'fr-FR' => "Quel est le plus court?",
                       )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices06',
            'method_name' => 'captcha_choices_least06',
            'values' => array('en-US' => "ladybug, mouse, cat, toddler, ".
                 "man, horse, elephant, giraffe, tall building, mountain",
                'fr-FR' => "coccinelle, souris, chat, enfant, homme, ".
                 "cheval, éléphant, girafe, grand bâtiment, montagne",
                      ))
    ),

    $question_choice_least_pair07 = array(
       'question' => array('identifier_string' => 'db_least_question07',
            'method_name' => 'captcha_question_least07',
               'values' => array('en-US' => "Which takes less time?",
                'fr-FR' => "Qui prend moins de temps?",
                       )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices07',
            'method_name' => 'captcha_choices_least07',
            'values' => array('en-US' => "blink, lick envelope, comb hair, ".
                 "apply makeup, watch a movie, run marathon, olympics, ".
                 "summer vacation, year",
                'fr-FR' => "clignotement, enveloppe lécher, ".
                 "peigner les cheveux, appliquer le maquillage, ".
                 "regarder un film, exécuter marathon, ".
                 "jeux olympiques, les vacances d'été, l'année",
                      ))
    ),

    $question_choice_least_pair08 = array(
       'question' => array('identifier_string' => 'db_least_question08',
            'method_name' => 'captcha_question_least08',
            'values' => array('en-US' => "Which is colder sounding?",
                'fr-FR' => "Quel est le sondage froid?",
                      )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices08',
            'method_name' => 'captcha_choices_least08',
            'values' => array('en-US' => "Pluto, polar exploring, skating, ".
                 "swimming pool, tea, steam, molten iron, sun",
                'fr-FR' => "Pluton, exploration polaire, patinage, piscine, ".
                 "thé, vapeur, fer fondu, le soleil",
                     ))
    ),

    $question_choice_least_pair09 = array(
       'question' => array('identifier_string' => 'db_least_question09',
            'method_name' => 'captcha_question_least09',
            'values' => array('en-US' => "Which is the newest?",
                'fr-FR' => "Quel est le plus récent?",
                     )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices09',
            'method_name' => 'captcha_choices_least09',
            'values' => array('en-US' => "fresh milk, current president, ".
                 "your grandparents, Flight at Kitty Hawk, the Black Death, ".
                 "Egyptian Pyramids, writing, cave paintings, dinosaurs",
                'fr-FR' => "lait frais, actuel président, ". 
                 "vos grands-parents, vol à Kitty Hawk, la peste noire, ". 
                 "pyramides égyptiennes, l'écriture, ". 
                 "les peintures rupestres, des dinosaures",
                    ))
    ),

    $question_choice_least_pair10 = array(
       'question' => array('identifier_string' => 'db_least_question10',
            'method_name' => 'captcha_question_least10',
            'values' => array('en-US' => "Which holds less?",
                'fr-FR' => "Qui détient moins?",
                    )),
       'choices' => array('identifier_string' => 'db_least_captcha_choices10',
            'method_name' => 'captcha_choices_least10',
            'values' => array('en-US' => "teaspoon, saucer, cup, bowl, ". 
                "teapot, wash basin, barrel, pickup truck, moving van, ". 
                "oil tanker",
                'fr-FR' => "cuillère à café, soucoupe, tasse, bol, théière, ".
                 "lavabo, baril, camionnette, camion de déménagement, ".
                 "pétrolier",
                    ))
    ),
);


$preferences_questions_and_choices = array(
    //Preferences-Most Questions

    $preferences_choice_most_pair01 = array(
       'question' => array('identifier_string' => 'db_most_prefquestion01',
            'method_name' => 'preferences_question_most01',
            'values' => array('en-US' => "Animal you like the best:",
                'fr-FR' => "Animal que vous aimez le mieux:",
                     )),
       'choices' => array('identifier_string' => 'db_most_prefchoices01',
            'method_name' => 'preferences_choices_most01',
            'values' => array('en-US' => "ant, bunny, cat, cockroach, ".
                 "dog, goldfish, hamster, horse, snake, tiger, whale",
                'fr-FR' => "fourmi, lapin, chat, cafard, chien, ". 
                 "poisson d'or, hamster, cheval, serpent, ". 
                 "araignée, tigre, baleine",
                    ))
    ),

    $preferences_choice_most_pair02 = array(
       'question' => array('identifier_string' => 'db_most_prefquestion02',
            'method_name' => 'preferences_question_most02',
            'values' => array('en-US' => "Color you like the most:",
                'fr-FR' => "Couleur que vous aimez le plus:",
                     )),
       'choices' => array('identifier_string' => 'db_most_prefchoices02',
            'method_name' => 'preferences_choices_most02',
            'values' => array('en-US' => "no color, aquamarine, blue, ". 
                 "brown, gold, green, gray, mauve, pink, periwinkle, ". 
                 "purple, red, silver, turquoise, yellow",
                'fr-FR' => "pas de couleur, aigue-marine, bleu, brun, or, ".
                 "vert, gris, mauve, rose, bigorneau, pourpre, rouge, ".
                 "argent, turquoise, jaune",
                    ))
    ),

    $preferences_choice_most_pair03 = array(
       'question' => array('identifier_string' => 'db_most_prefquestion03',
            'method_name' => 'preferences_question_most03',
            'values' => array('en-US' => "Food you like the most:",
                'fr-FR' => "Nourriture que vous aimez le plus:",
                     )),
       'choices' => array('identifier_string' => 'db_most_prefchoices03',
            'method_name' => 'preferences_choices_most03',
            'values' => array('en-US' => "apple, banana, chicken, fish, ".
                 "lamb, nuts, orange, pork, potato, tomato, steak",
                'fr-FR' => "pomme, banane, poulet, poisson, agneau, ".
                 "noix, orange, porc, pomme de terre, la tomate, le steak",
                    ))
    ),

    $preferences_choice_most_pair04 = array(
       'question' => array('identifier_string' => 'db_most_prefquestion04',
            'method_name' => 'preferences_question_most04',
            'values' => array('en-US' => "Drink you like the most:",
                'fr-FR' => "Boisson vous aimez le plus:",
                     )),
       'choices' => array('identifier_string' => 'db_most_prefchoices04',
            'method_name' => 'preferences_choices_most04',
            'values' => array('en-US' => "apple juice, beer, coffee, ". 
                 "hot tea, ice tea, lemonade, orange juice, soda, ". 
                 "sparkling water, still water, wine",
                'fr-FR' => "jus de pomme, bière, café, thé chaud, ". 
                 "thé glacé, limonade, jus d'orange, soda, ". 
                 "eau gazeuse, eau plate, vin",
                    ))
    ),

    $preferences_choice_most_pair05 = array(
       'question' => array('identifier_string' => 'db_most_prefquestion05',
            'method_name' => 'preferences_question_most05',
            'values' => array('en-US' => "Game you like the most:",
                'fr-FR' => "Jeu que vous aimez le plus:",
                     )),
       'choices' => array('identifier_string' => 'db_most_prefchoices05',
            'method_name' => 'preferences_choices_most05',
            'values' => array('en-US' => "basketball, backgammon, checkers, ".
                 "chess, football, hockey, skate, ski, tennis, volleyball",
                'fr-FR' => "basket-ball, backgammon, dames, échecs, ".
                 "football, hockey, patin, ski, tennis, volley-ball",
                    ))
    ),

    $preferences_choice_most_pair06 = array(
       'question' => array('identifier_string' => 'db_most_prefquestion06',
            'method_name' => 'preferences_question_most06',
            'values' => array('en-US' => "Sound you like the most:",
                'fr-FR' => "Son que vous aimez le plus: ",
                     )),
       'choices' => array('identifier_string' => 'db_most_prefchoices06',
            'method_name' => 'preferences_choices_most06',
            'values' => array('en-US' => "accordion, drum, flute, guitar, ".
                 "harmonica, harp, horn, oboe, piano, triangle, trumpet, ".
                 "violin, whistle, xylophone",
                'fr-FR' => "accordéon, tambour, flûte, guitare, harmonica, ".
                 "harpe, cor, hautbois, piano, triangle, trompette, violon, ".
                 "sifflet, xylophone",
                    ))
    ),

    //Preferences-Least Questions

    $preferences_choice_least_pair01 = array(
       'question' => array('identifier_string' => 'db_least_prefquestion01',
            'method_name' => 'preferences_question_least01',
            'values' => array('en-US' => "Animal you like the least:",
                'fr-FR' => "Animal que vous aimez le moins:",
                     )),
       'choices' => array('identifier_string' => 'db_least_prefchoices01',
            'method_name' => 'preferences_choices_least01',
            'values' => array('en-US' => "ant, bunny, cat, cockroach, dog, ".
                 "goldfish, hamster, horse, snake, tiger, whale",
                'fr-FR' => "fourmi, lapin, chat, cafard, chien, ".
                 "poisson d'or, hamster, cheval, serpent, araignée, ".
                 "tigre, baleine",
                    ))
    ),

    $preferences_choice_least_pair02 = array(
       'question' => array('identifier_string' => 'db_least_prefquestion02',
            'method_name' => 'preferences_question_least02',
            'values' => array('en-US' => "Color you like the least:",
                'fr-FR' => "Couleur que vous aimez le moins:",
                     )),
       'choices' => array('identifier_string' => 'db_least_prefchoices02',
            'method_name' => 'preferences_choices_least02',
            'values' => array('en-US' => "no color, aquamarine, blue, ". 
                 "brown, gold, green, gray, mauve, pink, periwinkle, ". 
                 "purple, red, silver, turquoise, yellow",
                'fr-FR' => "pas de couleur, aigue-marine, bleu,brun, or, ".
                 "vert, gris, mauve, rose, bigorneau, pourpre, rouge, ". 
                 "argent, turquoise, jaune",
                    ))
    ),

    $preferences_choice_least_pair03 = array(
       'question' => array('identifier_string' => 'db_least_prefquestion03',
            'method_name' => 'preferences_question_least03',
            'values' => array('en-US' => "Food you like the least:",
                'fr-FR' => "Nourriture que vous aimez le moins:",
                     )),
       'choices' => array('identifier_string' => 'db_least_prefchoices03',
            'method_name' => 'preferences_choices_least03',
            'values' => array('en-US' => "apple, banana, chicken, fish, ".
                 "lamb, nuts, orange, pork, potato, tomato, steak",
                'fr-FR' => "pomme, banane, poulet, poisson, agneau, noix, ".
                 "orange, porc, pomme de terre, la tomate, le steak",
                    ))
    ),

    $preferences_choice_least_pair04 = array(
       'question' => array('identifier_string' => 'db_least_prefquestion04',
            'method_name' => 'preferences_question_least04',
            'values' => array('en-US' => "Drink you like the least:",
                'fr-FR' => "Boisson vous aimez le moins:",
                     )),
       'choices' => array('identifier_string' => 'db_least_prefchoices04',
            'method_name' => 'preferences_choices_least04',
            'values' => array('en-US' => "apple juice, beer, coffee, ". 
                 "hot tea, ice tea, lemonade, orange juice, soda, ". 
                 "sparkling water, still water, wine",
                'fr-FR' => "jus de pomme, bière, café, thé chaud, ". 
                 "thé glacé, limonade, jus d'orange, soda, ". 
                 "eau gazeuse, eau plate, vin",
                    ))
    ),

    $preferences_choice_least_pair05 = array(
       'question' => array('identifier_string' => 'db_least_prefquestion05',
            'method_name' => 'preferences_question_least05',
            'values' => array('en-US' => "Game you like the least:",
                'fr-FR' => "Jeu que vous aimez le moins:",
                     )),
       'choices' => array('identifier_string' => 'db_least_prefchoices05',
            'method_name' => 'preferences_choices_least05',
            'values' => array('en-US' => "basketball, backgammon, ". 
                 "checkers, chess, football, hockey, skate, ski, ". 
                 "tennis, volleyball",
                'fr-FR' => "basket-ball, backgammon, dames, échecs, ". 
                 "football, hockey, patin, ski, tennis, volley-ball",
                    ))
    ),

    $preferences_choice_least_pair06 = array(
       'question' => array('identifier_string' => 'db_least_prefquestion06',
            'method_name' => 'preferences_question_least06',
            'values' => array('en-US' => "Sound you like the least:",
                'fr-FR' => "Son que vous aimez le moins: ",
                     )),
       'choices' => array('identifier_string' => 'db_least_prefchoices06',
            'method_name' => 'preferences_choices_least06',
            'values' => array('en-US' => "accordion, drum, flute, guitar, ".
                 "harmonica, harp, horn, oboe, piano, triangle, trumpet, ".
                 "violin, whistle, xylophone",
                'fr-FR' => "accordéon, tambour, flûte, guitare, harmonica, ".
                 "harpe, cor, hautbois, piano, triangle, trompette, violon, ".
                 "sifflet, xylophone",
                    ))
    ),
);


$i = 1;
foreach($activities as $activity => $translation_info) {
    // set-up activity
    $db->execute("INSERT INTO ACTIVITY VALUES ($i, $i, '$activity')");
    //give admin role the ability to have that activity
    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".ADMIN_ROLE.", $i)");
    $db->execute("INSERT INTO TRANSLATION
            VALUES($i, '{$translation_info[0]}')");
    foreach($translation_info[1] as $locale_tag => $translation) {
        $index = $locale_index[$locale_tag];
        $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES ($i, $index,
            '$translation')");

    }

    $i++;
}

foreach($captcha_questions_and_choices as $captcha_question_and_choice) {
    $question_data = $captcha_question_and_choice['question'];
    $choices_data = $captcha_question_and_choice['choices'];
    $identifier_string_question = $question_data['identifier_string'];
    $identifier_string_choices = $choices_data['identifier_string'];
    $method_name_question = $question_data['method_name'];
    $method_name_choices = $choices_data['method_name'];
    $values_question = $question_data['values'];
    $values_choices = $choices_data['values'];

    $sql_question_translation =  "INSERT INTO TRANSLATION ". 
            "(IDENTIFIER_STRING) VALUES(?)";
    $question_translation_result = $db->execute($sql_question_translation,
            array($identifier_string_question));
    if($question_translation_result) {
        $translation_id_question = $db->insertID();
    }
    $sql_choices_translation =  "INSERT INTO TRANSLATION ". 
            "(IDENTIFIER_STRING) VALUES(?)";
    $choices_translation_result = $db->execute($sql_choices_translation,
            array($identifier_string_choices));
    if($choices_translation_result) {
        $translation_id_choices = $db->insertID();
    }
    $sql_mapping = "INSERT INTO QUESTION_CHOICE_MAPPING ".
            "(TRANSLATION_ID_QUESTION, TRANSLATION_ID_CHOICES) ".
            "VALUES (?,?)";
    $result_mapping = $db->execute($sql_mapping,
            array($translation_id_question, $translation_id_choices));
    if(!$result_mapping) {
        return false;
    }

    $sql_question = "INSERT INTO CAPTCHA (TRANSLATION_ID, METHOD_NAME) ".
            "VALUES (?,?)";
    $result_question = $db->execute($sql_question,
            array($translation_id_question, $method_name_question));
    if((!$result_question)) {
        return false;
    }

    foreach($question_data['values'] as $locale_tag => $translation_question) {
        $index_captcha_question_and_choices = $locale_index[$locale_tag];
        $db->execute("INSERT INTO TRANSLATION_LOCALE
            VALUES ($translation_id_question,
            $index_captcha_question_and_choices,
            '".$db->escapeString($translation_question)."')");
     }
     foreach($choices_data['values'] as $locale_tag => $translation_choices) {
         $index_captcha_question_and_choices = $locale_index[$locale_tag];
         $db->execute("INSERT INTO TRANSLATION_LOCALE
             VALUES ($translation_id_choices,
             $index_captcha_question_and_choices,
             '".$db->escapeString($translation_choices)."')");
    }
    $sql_choices = "INSERT INTO CAPTCHA (TRANSLATION_ID, METHOD_NAME) VALUES (?,?)";
       $result_choices = $db->execute($sql_choices,
            array($translation_id_choices, $method_name_choices));
       if((!$result_choices)) {
           return false;
       }

}

foreach($preferences_questions_and_choices as
            $preferences_question_and_choice) {

    $questionpref_data = $preferences_question_and_choice['question'];
    $choicespref_data = $preferences_question_and_choice['choices'];
    $identifierpref_string_question = $questionpref_data['identifier_string'];
    $identifierpref_string_choices = $choicespref_data['identifier_string'];
    $methodpref_name_question = $questionpref_data['method_name'];
    $methodpref_name_choices = $choicespref_data['method_name'];
    $valuespref_question = $questionpref_data['values'];
    $valuespref_choices = $choicespref_data['values'];

    $sqlpref_question_translation =  "INSERT INTO TRANSLATION ". 
            "(IDENTIFIER_STRING) VALUES(?)";
    $questionpref_translation_result =
            $db->execute($sqlpref_question_translation,
            array($identifierpref_string_question));
    if($questionpref_translation_result) {
        $translation_id_questionpref = $db->insertID();
    }
    $sqlpref_choices_translation =  "INSERT INTO TRANSLATION ".
            "(IDENTIFIER_STRING) VALUES(?)";
    $choicespref_translation_result =
            $db->execute($sqlpref_choices_translation,
            array($identifierpref_string_choices));
    if($choicespref_translation_result) {
        $translation_id_choicespref = $db->insertID();
    }

    $sql_mapping = "INSERT INTO QUESTION_CHOICE_MAPPING ".
            "(TRANSLATION_ID_QUESTION, TRANSLATION_ID_CHOICES) ".
            "VALUES (?,?)";
    $result_mapping = $db->execute($sql_mapping,
            array($translation_id_questionpref, $translation_id_choicespref));
    if(!$result_mapping) {
        return false;
    }

    $sqlpref_question = "INSERT INTO PREFERENCES ". 
            "(TRANSLATION_ID, METHOD_NAME) VALUES (?,?)";
    $resultpref_question = $db->execute($sqlpref_question,
            array($translation_id_questionpref, $methodpref_name_question));
    if((!$resultpref_question)) {
        return false;
    }

     foreach($questionpref_data['values'] as $locale_tag =>
            $translation_questionpref) {
       $index_preferences_question_and_choices = $locale_index[$locale_tag];
       $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
            ($translation_id_questionpref,
            $index_preferences_question_and_choices,
           '".$db->escapeString($translation_questionpref)."')");
    }
    foreach($choicespref_data['values'] as $locale_tag =>
            $translation_choicespref) {
         $index_captcha_question_and_choices = $locale_index[$locale_tag];
         $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
             ($translation_id_choicespref, $index_captcha_question_and_choices,
             '".$db->escapeString($translation_choicespref)."')");
    }
    $sqlpref_choices = "INSERT INTO PREFERENCES ". 
            "(TRANSLATION_ID, METHOD_NAME) VALUES (?,?)";
    $resultpref_choices = $db->execute($sqlpref_choices,
            array($translation_id_choicespref, $methodpref_name_choices));
    if((!$resultpref_choices)){
        return false;
    }
}

$new_user_activities = array(
    "manageAccount",
    "manageGroups",
    "mixCrawls",
    "groupFeeds",
    "captchaSettings"
);

foreach($new_user_activities as $new_activity) {
    $i = 1;
    foreach($activities as $key => $value) {
        if($new_activity == $key){
        //give new user role the ability to have that activity
            $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".
                USER_ROLE.", $i)");
        }
        $i++;
    }
}

$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634195',
    'YouTube', 'video', 'http://www.youtube.com/watch?v={}&',
    'http://i1.ytimg.com/vi/{}/default.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634196',
    'MetaCafe', 'video', 'http://www.metacafe.com/watch/{}/',
    'http://www.metacafe.com/thumb/{}.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634197',
    'DailyMotion', 'video', 'http://www.dailymotion.com/video/{}',
    'http://www.dailymotion.com/thumbnail/video/{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634198',
    'Vimeo', 'video', 'http://player.vimeo.com/video/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634199',
    'Break.com', 'video', 'http://www.break.com/index/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634200',
    'Yahoo News', 'rss', 'http://news.yahoo.com/rss/',
    '', 'en')");

$db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(2, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    2, 0, 1, 1, 'media:image site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(3, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    3, 0, 1, 1, 'media:video site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (4, 'news', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(4, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(4, 0, 1, 1,
    'media:news')");

$db->execute("INSERT INTO SUBSEARCH VALUES('db_subsearch_images',
    'images','m:2', 50)");
$db->execute("INSERT INTO TRANSLATION VALUES (1002,'db_subsearch_images')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 1, 'Images' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 2, 'الصور' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 5, 'Images' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 15, 'Hình' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 16, '图象' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 20, 'تصاویر' )");

$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_videos',
    'videos','m:3', 10)");
$db->execute("INSERT INTO TRANSLATION VALUES (1003,'db_subsearch_videos')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 1, 'Videos' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 2, 'فيديو' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 5, 'Vidéos' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 15, 'Thâu hình' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 16, '录影' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 20, 'ویدیوها' )");

$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_news',
    'news','m:4',20)");
$db->execute("INSERT INTO TRANSLATION VALUES (1004,'db_subsearch_news')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 1, 'News' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 2, 'أخبار' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 5, 'Actualités' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 15, 'Tin tức' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 16, '新闻' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 20, 'اخبار' )");



$db->disconnect();
if(in_array(DBMS, array('sqlite','sqlite3' ))){
    chmod(CRAWL_DIR."/data/".DB_NAME.".db", 0666);
}

echo "Create DB succeeded\n";
?>
