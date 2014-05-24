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
 * @author Akash Patel (edited by Chris Pollett chris@pollett.org)
 * @package seek_quarry
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

/*
 *  Returns RSA like modulus.
 *
 *  @param String id identifier of a hidden input field containing the
 *      modulus to use in the Fiat Shamir Protocol
 *  @return BigInt RSA-like modulus.
 */
function getN(id)
{
    var n = elt(id).value;
    return str2BigInt(n, 10, 0);
}

/*
 *  Generates Fiat shamir parameters such as x and y and append
 *  with the input form
 *
 *  @param Object fiat_shamir_id id of the form
 *  @param String sha1 of the user password
 *  @param int e random value sent by the server. Either 0 or 1.
 *  @param String user_name id of the form
 *  @param String modulus_id identifier of hidden field with modulus to use in
 *      Fiat Shamir
 */
function dynamicForm(zkp_form_id, sha1, e, user_name, modulus_id)
{
    var zkp_form = elt(zkp_form_id);
    var n = getN(modulus_id);
    var r = getR();
    var x = multMod(r, r, n);
    var y = getY(sha1, e, r, n);
    var input_x = ce('input');
    input_x.type = 'hidden';
    input_x.name = 'x';
    input_x.value = bigInt2Str(x, 10);
    var input_y = ce('input');
    input_y.type = 'hidden';
    input_y.name = 'y';
    input_y.value = bigInt2Str(y, 10);
    var input_username = ce('input');
    input_username.type = 'hidden';
    input_username.name = 'u';
    input_username.value = user_name;
    zkp_form.appendChild(input_x);
    zkp_form.appendChild(input_y);
    zkp_form.appendChild(input_username);
    zkp_form.submit();
}

/*
 *  Generates Fiat shamir parameters Y. When user gives username, password
 *  for the first time it stored the password on the cookie. From rest of
 *  the Fiat shamir iteration it uses password stores on the client side
 *  cookie.
 *
 *  @param String sha1 sha1 of the password
 *  @param int e random value sent by the server. Either 0 or 1.
 *  @param BigInt r random value picked by the client
 *  @param BigInt n RSA like modulus
 *  @return BigInt y fiat-shamir parameter Y.
 */
function getY(sha1, e, r, n)
{
    var s = str2BigInt(sha1, 16, 0);
    var se;
    if (e == 0) {
        se = '1';
        se = str2BigInt(se, 10, 0);
    } else {
        se = s;
    }
    y = multMod(r, se, n);
    return y;
}

/*
 *  Generates random number and converts it into BigInteger.
 *
 *  @return BigInteger final_r random BigInteger
 */
function getR()
{
    var r = Math.floor((Math.random() * 21474) + 1);
    r = r.toString();
    var final_r = str2BigInt(r, 10, 0);
    return final_r;
}

/*
 *  Generates Fiat shamir parameters such as x and y and append
 *  with the input form. This method calls first time when user
 *  provides user name and password
 *
 *  @param Object zkp_form_id identifier of the form with zkp data
 *  @param String username_id identifier of the form element with the username
 *  @param String password_id identifier of the form element with the password
 *  @param int e random value send by the server. Either 0 or 1.
 *  @param int auth_count number of Fiat-Shamir iterations
 */
function generateKeys(zkp_form_id, username_id, password_id,
    modulus_id, e, auth_count)
{
    var password = elt(password_id).value;
    var u = elt(username_id).value;
    var token_object = elt('CSRF-TOKEN');
    var token = token_object.value;
    var token_name = token_object.name;
    var sha1 = generateSha1(password);
    var n = new getN(modulus_id);
    for (var i = 0; i < auth_count - 1; i++) {
        var r = getR();
        var x = multMod(r, r, n);
        var y = getY(sha1, e, r, n);
        var x_string = bigInt2Str(x, 10);
        var y_string = bigInt2Str(y, 10);
        sendFiatShamirParameters(x_string, y_string, u, token, token_name);
        var e_temp = elt("salt-value").value;
        e_temp = e_temp + '';
        e = parseInt(e_temp);
        if(e == -1) {
            e = 1;
            break;
        }
    }
    elt(password_id).value = null;
    dynamicForm(zkp_form_id, sha1, e, u, modulus_id);
}

/*
 *  Sends Fiat-Shamir via AJAX parameters and receives parameter e from server
 *
 *  @param BigInt x Fiat-Shamir parameter x
 *  @param BigInt y Fiat-Shamir parameter y
 *  @param String u username provided by user
 *  @param String token CSRF token sent by the server
 *  @param String token_name name to use for CSRF token
 */
function sendFiatShamirParameters(x, y, u, token, token_name)
{
    var http = new XMLHttpRequest();
    var url = "./";
    var params = "c=admin&x=" + x + "&y=" + y +"&u=" + u +
        "&"+token_name+"=" + token;
    http.open("post", url, false);
    http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http.setRequestHeader("Content-length", params.length);
    http.setRequestHeader("Connection", "close");
    http.onreadystatechange = function () {
        if (http.readyState == 4 && http.status == 200) {
            elt("salt-value").value = http.responseText;
        }
    }
    http.send(params);
}

/*
 *  This function is used during create account module and when
 *  authentication mode is ZKP.
 *
 *  @param String password_id
 *  @param String repassword_id
 *  @param String 
 */
function registration(password_id, repassword_id, modulus_id)
{
    var password = elt(password_id);
    var repassword = elt(repassword_id);
    var sha1 = generateSha1(password.value);
    var x = str2BigInt(sha1, 16, 0);
    var n = getN(modulus_id);
    var z = multMod(x, x, n);
    password.value = bigInt2Str(z, 10);
    repassword.value = bigInt2Str(z, 10);
}
