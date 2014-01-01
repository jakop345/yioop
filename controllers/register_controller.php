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
    var $views = array("register", "search");
    /**
     * LocaleModel used to get the available languages/locales, CrawlModel
     * is used to get a list of available crawls
     * @var array
     */
    var $models = array("user");

    /**
     *  Allows users to create accounts.
     *  Validates the input form when creating an account
     *
     */

    function processRequest()
    {
        $data = array();
        if(isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $data[CSRF_TOKEN] = $this->generateCSRFToken($user);
        $token_okay = $this->checkCSRFToken(CSRF_TOKEN, $user);
        $regex_email=
            '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+'.
            '(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
        $data = array();
        $view = "register";
        $fields = array("first", "last", "user",
            "email", "password", "repassword");
        $data['SCRIPT'] = "";
        $error = false;
        if($token_okay && isset($_REQUEST['submit'])) {
            foreach($fields as $field) {
                if(empty($_REQUEST[$field]) || !isset($_REQUEST[$field])) {
                    $error = true;
                    $data[] = $field;
                } else if($field == "email" &&
                    !preg_match($regex_email,
                    $this->clean($_REQUEST['email'], "string" ))) {
                        $error = true;
                        $data[] = "email";
                }
            }
            if(isset($_REQUEST['password'])
                && isset($_REQUEST['repassword'])
                && $this->clean($_REQUEST['password'], "string" ) !=
                $this->clean($_REQUEST['repassword'], "string" )) {
                $error = true;
                $data[] = "password";
            }
            if($error) {
                $data['RESULT'] = "true";
                $data['FIRST'] = isset($_REQUEST['first']) ?
                    $this->clean($_REQUEST['first'], "string") : "";
                $data['LAST'] = isset($_REQUEST['last']) ?
                    $this->clean($_REQUEST['last'], "string") : "";
                $data['USER'] = isset($_REQUEST['user']) ?
                    $this->clean($_REQUEST['user'], "string") : "";
                $data['EMAIL'] = isset($_REQUEST['email']) ?
                    $this->clean($_REQUEST['email'], "string") : "";
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_error_fields')."</h1>')";
            } else {
                $view = "search";
/*$server = new MailServer('smtp.domain', 587, 'username', 'password', 'tls');
$to = "chris@pollett.org";
$from = "chris@pollett.org";
$subject = "Test Mail";
$message = "This is a test";
$server->send($subject, $from, $to, $message);*/
                $this->userModel->
                    registerUser($this->clean($_REQUEST['first'], "string" ),
                    $this->clean($_REQUEST['last'], "string" ),
                    $this->clean($_REQUEST['user'], "string" ),
                    $this->clean($_REQUEST['email'], "string" ),
                    $this->clean($_REQUEST['password'], "string" ));
                $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('register_controller_account_created')."</h1>')";
            }
        }
        $data[CSRF_TOKEN] = $this->generateCSRFToken(
                $_SERVER['REMOTE_ADDR']);
        $this->displayView($view, $data);
    }
}

?>
