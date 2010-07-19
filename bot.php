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
 * Web page used to display information about the crawler component of
 * the SeekQuarry/Yioop Search engine
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage bot
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

/** Calculate base directory of script */
define("BASE_DIR", substr($_SERVER['DOCUMENT_ROOT'].$_SERVER['PWD'].
    $_SERVER["SCRIPT_NAME"], 0, 
    -strlen("bot.php")));

/** Load search engine wide configuration file */
require_once BASE_DIR.'/configs/config.php';

if(!PROFILE) {echo "BAD REQUEST"; exit();}

?>
<!DOCTYPE html>

<html lang="en-US" dir="ltr">

<head>
    <title><?php echo USER_AGENT_SHORT; ?></title>

    <meta name="description" content=
        "A description of a robot based on the SeekQuarry/Yioop! Search Engine" 
        />

    <meta charset="utf-8" />
    <link rel="stylesheet" type="text/css" href="css/search.css" />  
</head>
<body>
<?php
    if(file_exists(WORK_DIRECTORY."/bot.txt")) {
        echo file_get_contents(WORK_DIRECTORY."/bot.txt");
    } else {
        echo "Unfortunately, the person who is using this software did not ".
            "provide a description of their user-agent";
    }
?>
</body>
</html>
