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
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

/*
 *  Returns RSA like modulus.
 *
 *  @param Object fiatShamirModulus id of the fiatShamirModulus dom object
 *  @return BigInteger RSA like modulus.
 */
function getN(fiatShamirModulus)
{
    var n = elt(fiatShamirModulus).value;
    return str2BigInt(n, 10, 0);
}

/*
 *  Generates Fiat shamir parameters such as x and y and append
 *  with the input form
 *
 *  @param Object form1 id of the form
 *  @param String sha1 of the user password
 *  @param int e random value sent by the server. Either 0 or 1.
 *  @param String user_name id of the form
 *  @param String fiat shamir modulus
 */
function dynamicForm(form1, sha1, e, user_name, fiatShamirModulus)
{
    var form = elt(form1);
    var n = getN(fiatShamirModulus);
    var r = getR();
    var x = multMod(r, r, n);
    var y = getY(sha1, e, r, n);
    var input_x = ce('input');
    input_x.type = 'hidden';
    input_x.name = 'x1';
    input_x.value = bigInt2Str(x, 10);
    var input_y = ce('input');
    input_y.type = 'hidden';
    input_y.name = 'y1';
    input_y.value = bigInt2Str(y, 10);
    var input_username = ce('input');
    input_username.type = 'hidden';
    input_username.name = 'u';
    input_username.value = user_name;
    form.appendChild(input_x);
    form.appendChild(input_y);
    form.appendChild(input_username);
    form.submit();
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
 *  Generates random number and convert into BigInteger.
 *
 *  @return BigInteger final_r random BigInteger
 */
function getR()
{
    var r = Math.floor((Math.random() * 21474) + 1);
    r = r.toString();;
    var final_r = str2BigInt(r, 10, 0);
    return final_r;
}

/*
 *  Generates Fiat shamir parameters such as x and y and append
 *  with the input form. This method calls first time when user
 *  provides user name and password
 *
 *  @param Object form1 id of the form
 *  @param String username username provided by user
 *  @param String password1 password provided by user
 *  @param int e random value send by the server. Either 0 or 1.
 *  @param int auth_count number of Fiat-Shamir iterations
 */
function generateKeys(form1, username, password1, fiatShamirModulus, e, auth_count)
{
    var password = elt(password1).value;
    var u = elt(username).value;
    var token = elt('YIOOP_TOKEN').value;
    var sha1 = generateSha1(password);
    var n = new getN(fiatShamirModulus);
    for (var i = 0; i < auth_count - 1; i++) {
        var r = getR();
        var x = multMod(r, r, n);
        var y = getY(sha1, e, r, n);
        var x1 = bigInt2Str(x, 10);
        var y1 = bigInt2Str(y, 10);
        sendFiatShamirParameters(x1, y1, u, token);
        var e_temp = elt("saltValue").value;
        e_temp = e_temp + '';
        e = parseInt(e_temp);
        if(e == -1){
            e = 1;
            break;
        }
    }
    elt(password1).value = null;
    dynamicForm(form1, sha1, e, u, fiatShamirModulus);
}

/*
 *  Make AJAX request to the server. This method sends Fiat-Shamir
 *  parameters and receives parameter e from server
 *
 *  @param BigInt x1 Fiat-Shamir parameter x
 *  @param BigInt y1 Fiat-Shamir parameter y
 *  @param String u username provided by user
 *  @param String token CSRF token sent by the server
 */
function sendFiatShamirParameters(x1, y1, u, token)
{
    var http = new XMLHttpRequest();
    var url = "./?c=admin";
    var params = "x1=" + x1 + "&y1=" + y1 +"&u=" + u + "&YIOOP_TOKEN=" + token;
    http.open("POST", url, false);
    http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http.setRequestHeader("Content-length", params.length);
    http.setRequestHeader("Connection", "close");
    http.onreadystatechange = function () {
        if (http.readyState == 4 && http.status == 200) {
            elt("saltValue").value = http.responseText;
        }
    }
    http.send(params);
}

/*
 *  This function is used during create account module and when
 *  authentication mode is ZKP.
 *
 *  @param String password password provided by user
 *  @param String repassword repassword provided by user
 */
function registration(password, repassword, fiatShamirModulus)
{
    var password1 = elt(password);
    var repassword1 = elt(repassword);
    var sha1 = generateSha1(password1.value);
    var x = str2BigInt(sha1, 16, 0);
    var n = getN(fiatShamirModulus);
    var z = multMod(x, x, n);
    password1.value = bigInt2Str(z, 10);
    repassword1.value = bigInt2Str(z, 10);
}
