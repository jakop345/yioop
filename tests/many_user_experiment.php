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
 * Test to see for big strings which how long various string concatenation
 * operations take.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage test
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
 * This script inserts 500 users into the database so that one can
 * test UI of Yioop in a scenario that has a moderate number of users.
 * It then insert groups for these users
 */


/**
 * Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/tests")));
require_once BASE_DIR.'/configs/config.php';


/** Get base class for profile_model.php*/
require_once BASE_DIR."/models/model.php";

/** For UserModel::addUser method*/
require_once BASE_DIR."/models/user_model.php";

/** To create groups that can add users to */
require_once BASE_DIR."/models/group_model.php";

/** To create roles that can add users to */
require_once BASE_DIR."/models/role_model.php";

/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

$user_model = new UserModel();

//Add lots of users
$user_ids = array();
for($i = 0; $i < 500; $i++) {
    echo "Adding User $i\n";
    $id = $user_model->addUser("User$i", "test", "First$i", "Last$i",
        "user$i@email.net", ACTIVE_STATUS);
    if($id === false) {
        echo "Problem inserting user into DB, aborting...\n";
        exit(1);
    }
    $user_ids[$i] = $id;
}

// add lots of groups
$group_model = new GroupModel();
$group_ids = array();
for($i = 0; $i < 100; $i++) {
    echo "Creating Group $i\n";
    $group_ids[$i] = $group_model->addGroup("Group$i", $user_ids[$i],
        PUBLIC_JOIN, GROUP_READ_WRITE);
}

// add lots of users to group 1

for($i = 0; $i < 100; $i++) {
    $user_id = $user_ids[$i + 2];
    $group_model->addUserGroup($user_id, $group_ids[1], ACTIVE_STATUS);
}


// add lots of roles
$role_model = new RoleModel();
$user_id = $user_ids[2];
for($i = 0; $i < 100; $i++) {
    echo "Creating Role $i\n";
    $role_model->addRole("Role$i");
    $role_id =  $role_model->getRoleId("Role$i");
    $role_model->addUserRole($user_id, $role_id);
}

?>
