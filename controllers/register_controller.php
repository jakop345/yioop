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
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load base controller class, if needed
 */
require_once BASE_DIR."/controllers/controller.php";
/**
 * Timing functions
 */
require_once BASE_DIR."/lib/mail_server.php";


/**
 * Controller used to handle search requests to SeekQuarry
 * search site. Used to both get and display
 * search results.
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage controller
 */
class RegisterController extends Controller
{
    /**
     * Load the RegisterView
     * @var array
     */
    var $views = array("register", "signin");
    /**
     * LocaleModel used to get the available languages/locales, CrawlModel
     * is used to get a list of available crawls
     * @var array
     */
    var $models = array("user");

    var $activities = array("createAccount", "processAccountData",
        "resetPassword", "emailVerification");

    var $register_fields = array("first", "last", "user",
            "email", "password", "repassword");
    /**
     *  Allows users to create accounts.
     *  Validates the input form when creating an account
     *
     */
    function processRequest()
    {
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $activity = isset($_REQUEST['a']) ? 
            $this->clean($_REQUEST['a'], 'string') : 'createAccount';
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);

        if(!in_array($activity, $this->activities) || !$token_okay) {
            $activity = 'createAccount';
        }
        $data = $this->call($activity);
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        $view = (isset($data['REFRESH'])) ? $data['REFRESH'] : 'register';
        $this->displayView($view, $data);
    }

    /**
     *
     */
    function createAccount()
    {
        $data = array();
        $fields = $this->register_fields;
        foreach($fields as $field) {
            $data[strtoupper($field)] = "";
        }
        return $data;
    }

    /**
     *
     */
    function processAccountData()
    {
        $data["ELEMENT"] = "manageaccountElement";
        $data['SCRIPT'] = "";
        $data['MESSAGE'] = "";
        $data['SCRIPT'] = "";
        $error = $this->getCleanFields($data);
        if($error) {
            $data['RESULT'] = "true";
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_error_fields')."</h1>')";
            $data['REFRESH'] = "register";
            return $data;
        }
        if($this->userModel->getUserId($data['USER'])) {
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
            tl('register_controller_user_already_exists')."</h1>')";
            $data['REFRESH'] = "register";
            return $data;
        }
        switch(REGISTRATION_TYPE)
        {
            case 'no_activation':
                $data['REFRESH'] = "signin";
                $this->userModel->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL']);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_created')."</h1>')";
            break;
            case 'email_registration':
/*$server = new MailServer('smtp.domain', 587, 'username', 'password', 'tls');
$to = "chris@pollett.org";
$from = "chris@pollett.org";
$subject = "Test Mail";
$message = "This is a test";
$server->send($subject, $from, $to, $message);*/
            break;
            case 'admin_activation':
                $data['REFRESH'] = "signin";
                $this->userModel->addUser($data['USER'], $data['PASSWORD'],
                    $data['FIRST'], $data['LAST'], $data['EMAIL'], true);
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                    tl('register_controller_account_request_made')."</h1>')";
                $server = new MailServer(MAIL_SENDER, MAIL_SERVER,
                    MAIL_SERVERPORT, MAIL_USERNAME, MAIL_PASSWORD,
                    MAIL_SECURITY);
                $subject = tl('register_controller_admin_activation_request');
                $message = tl('register_controller_admin_activation_message',
                    $data['FIRST'], $data['LAST'], $data['USER']);
                $server->send($subject, MAIL_SENDER, MAIL_SENDER, $message);
            break;
        }
        return $data;
    }

    /**
     *
     */
    function resetPassword()
    {

    }

    /**
     *
     */
    function emailVerification()
    {
    }

    function getCleanFields(&$data)
    {
        $fields = $this->register_fields;
        $missing = array();
        $regex_email=
            '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+'.
            '(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
        $error = false;
        foreach($fields as $field) {
            if(empty($_REQUEST[$field]) || !isset($_REQUEST[$field])) {
                $error = true;
                $missing[] = $field;
                $data[strtoupper($field)] = "";
            } else if($field == "email" &&
                !preg_match($regex_email,
                $this->clean($_REQUEST['email'], "string" ))) {
                $error = true;
                $missing[] = "email";
                $data[strtoupper($field)] = "";
            } else {
                $data[strtoupper($field)] = $this->clean($_REQUEST[$field],
                    "string");
            }
        }
        if(isset($_REQUEST['password'])
            && isset($_REQUEST['repassword'])
            && $this->clean($_REQUEST['password'], "string" ) !=
            $this->clean($_REQUEST['repassword'], "string" )) {
            $error = true;
            $missing[] = "password";
            $missing[] = "repassword";
            $data["PASSWORD"] = "";
            $data["REPASSWORD"] = "";
        }
        $data['MISSING'] = $missing;
        return $error;
    }
}

?>
