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
 * Constant for number of bits per element
 */
var bits_per_element = 15;

/*
 * Constant to mask the overflow value
 */
var mask = 32767;

/*
 * Constant to define radix
 */
var radix = mask + 1;

/*
 *  To add bigInt and int number
 *
 *  @param bigInt x first operand
 *  @param int n second operand
 *  @return bigInt result
 */
function addInteger(x, n)
{
  var new_x = expandBigInt(x, x.length + 1);
  addBigIntToInt(new_x, n);
  return trimBigInt(new_x, 1);
}

/*
 *  To return the bigInt with given number of leading zeroes
 *
 *  @param bigInt x input number
 *  @param int k expected number of leading zeroes
 *  @return bigInt result
 */
function trimBigInt(x, k)
{
  var i = x.length;
  while(i > 0 && !x[i-1]){
      i--;
  }
  var y = new Array(i + k);
  copyBigIntToBigInt(y, x);
  return y;
}

/*
 *  To expand bigInt with atleast n elements.
 *  adding zeroes if needed
 *
 *  @param bigInt x first operand
 *  @param int n expected number of elements
 *  @return bigInt result
 */
function expandBigInt(x, n)
{
  var bits = (x.length > n ? x.length : n) * bits_per_element;
  var result = int2BigInt(0, bits, 0);
  copyBigIntToBigInt(result, x);
  return result;
}

/*
 *  To convert normal int to BigInt.Pad the array with leading zeros so
 *  that it has at least minSize elements
 *
 *  @param int t input number
 *  @param int bits expected number of bits
 *  @param int minSize minimum size of the BigInt.
 *  @return Array stores the bigInt in bits_per_element-bit chunks,
 *      little endian
 */
function int2BigInt(t, bits, minSize)
{
  var size_of_array = Math.ceil(bits / bits_per_element) + 1;
  var buffer = new Array(size_of_array);
  copyBigIntToInt(buffer, t);
  return buffer;
}

/*
 *  To copy one bigInt to another bigInt
 *  x must be an array at least as big as y
 *
 *  @param bigInt x input number
 *  @param bigInt bits expected number of bits
 *  @return bigInt result
 */
function copyBigIntToBigInt(x, y)
{
  var length = x.length < y.length ? x.length : y.length;
  for (var i = 0; i < length; i++){
    x[i] = y[i];
  }
  for (var i = length; i < x.length ; i++){
    x[i] = 0;
  }
}

/*
 *  To copy one bigInt to another int
 *
 *  @param bigInt x input number
 *  @param  int n input number
 *  @return bigInt result
 */
function copyBigIntToInt(x,n)
{
  var i, c;
  for (c = n,i = 0; i < x.length; i++) {
    x[i] = c & mask;
    c >>= bits_per_element;
  }
}

/*
 *  To perform x=x+n where x is a bigInt and n is an integer
 *
 *  @param bigInt x input number
 *  @param  int n input number
 *  @return bigInt result of the summation
 */
function addBigIntToInt(x, n)
{
  var i, c, b;
  x[0] += n;
  c = 0;
  for (i = 0; i < x.length; i++) {
    c += x[i];
    b = 0;
    if (c < 0) {
      b =- (c >> bits_per_element);
      c += b * radix;
    }
    x[i] = c & mask;
    c = (c >> bits_per_element) - b;
    if (!c) {
    return;
    }
  }
}

/*
 *  To convert the string into the BigInt
 *  Pad the array with leading zeros so that it has at least minSize elements.
 *  The array will always have at least one leading zero, unless base=-1
 *
 *  @param String s input string
 *  @param  int base base of the output number
 *  @param  int minSize minimum size of the bigInt
 *  @return bigInt
 */
function str2BigInt(s, base, min_size)
{
  var d, i, j, x, y, kk;
  var digits_str = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  var k = s.length;
  var x = int2BigInt(0, base * k, 0);
   for (i = 0; i < k; i++) {
    d = digits_str.indexOf(s.substring(i, i + 1), 0);
    if (base <= 36 && d >= 36) {
      d -= 26;
    }
    if (d >= base || d < 0) {
      break;
    }
    multInt(x, base);
    addBigIntToInt(x, d);
  }
  for (k = x.length; k > 0 && !x[k-1]; k --);
  k = min_size > k+1 ? min_size : k+1;
  y = new Array(k);
  kk = k < x.length ? k : x.length;
  for (i = 0; i < kk; i++){
    y[i] = x[i];
  }
  for (;i < k;i++) {
    y[i] = 0;
  }
  return y;
}

/*
 *  convert a bigInt into a string in a given base, from base 2 up to base 95
 *
 *  @param bigInt x input number
 *  @param  int base base of the output number
 *  @return String result
 */
function bigInt2Str(x, base)
{
  var i,t,s = "";
  var digits_str = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
  var temp_array = new Array();
  if (temp_array.length != x.length){
    temp_array = dup(x);
  } else {
    copyBigIntToBigInt(temp_array, x);
  }
  while (!isZero(temp_array)) {
      t = divInt(temp_array, base);  //t=s6 % base; s6=floor(s6/base);
      s = digits_str.substring(t, t+1) + s;
    }
   if (s.length == 0){
    s = "0";
   }
  return s;
}

/*
 *  To make the copy of the bigInt
 *
 *  @param bigInt x input number
 *  @return bigInt buffer copy of the bigInt x
 */
function dup(x)
{
 
  var buffer = new Array(x.length);
  copyBigIntToBigInt(buffer, x);
  return buffer;
}

/*
 *  To check whether bigInt is zero or not.
 *  Returns 0 if the bigInt is zero otherwise return 1
 *  @param bigInt x input number
 *  @return int
 */
function isZero(x) {
  for (var i = 0; i < x.length; i++){
    if (x[i]) {
      return 0;
    }
  }
  return 1;
}

/*
 *  To do x=floor(x/n) for bigInt x and integer n, and
 *
 *  @param bigInt x numerator
 *  @param int n denomenator
 *  @return int r reminder
 */
function divInt(x, n)
{
  var r = 0, s;
  for (var i = x.length-1; i >= 0; i--) {
    s = r*radix + x[i];
    x[i] = Math.floor(s/n);
    r = s%n;
  }
  return r;
}

/*
 *  To multiply bigInt with Int.
 *
 *  @param bigInt x  input number
 *  @param int n input number
 */
function multInt(x, n)
{
  if (n == 0){
    return;
  }
  var i, carry, borrow;
  carry = 0;
  for (i = 0; i < x.length ; i++) {
    carry += x[i] * n;
    borrow = 0;
    if (carry < 0) {
      borrow =- (carry >> bits_per_element);
      carry += borrow * radix;
    }
    x[i] = carry & mask;
    carry = (carry >> bits_per_element) - borrow;
  }
}

/*
 *  Performs x = x * y.
 *
 *  @param bigInt x  input number
 *  @param bigInt y  input number
 *  @return bigInt ans result of multiplication
 */
function mult(x, y)
{

  var ans = expandBigInt(x, x.length + y.length);
  mult_(ans,y);
  return trimBigInt(ans,1);
}

/*
 *  Performs x = x * y. Stores the result in x
 *
 *  @param bigInt x  input number
 *  @param bigInt y  input number
 */
function mult_(x, y) {
  var result = new Array(2 * x.length);
  copyBigIntToInt(result, 0);
  for (var i = 0; i< y.length; i++){
    if (y[i]){
      linearCombShift(result, x, y[i], i);
    }
  }
  copyBigIntToBigInt(x, result);
}

/*
 *  To perform linear combination
 *  for bigInts x and y, and integers a, b and ys
 *
 *  @param bigInt x  to store the result
 *  @param bigInt y  input number
 *  @param integer b  single digit of the second number
 *  @param integer ys  to get bit shift operator
 */
function linearCombShift(x, y, b, ys)
{
  var i, c, k;
  k = x.length < ys + y.length ? x.length : ys + y.length;
  for(c = 0,i = ys; i < k; i++) {
    c += x[i] + b * y[i-ys];
    x[i] = c & mask;
    c >>= bits_per_element;
  }
  for (i = k; c && i < x.length; i++) {
    c  += x[i];
    x[i] = c & mask;
    c >>= bits_per_element;
  }
}

/*
 *  To perform divide operation
 *
 *  @param bigInt x  Dividend
 *  @param bigInt y  Divisor
 *  @param bigInt q  to store the quotient
 *  @param integer r to store the reminder
 */
function divide(x, y, q, r)
{
  var kx, ky;
  var i, j, y1, y2, c, a, b;
  copyBigIntToBigInt(r, x);
  for (ky = y.length; y[ky-1] == 0; ky--);
  b = y[ky-1];
  for (a = 0; b; a++)
  b >>=1;
  a = bits_per_element - a;
  leftShift(y, a);
  leftShift(r, a);
  for (kx = r.length; r[kx-1] == 0 && kx > ky; kx--);
  copyBigIntToInt(q, 0);
  while (!greaterShift(y, r, kx - ky)) {
    subShift(r, y, kx-ky);
    q[kx - ky]++;
  }
  for (i = kx-1; i >= ky; i--) {
      if (r[i] == y[ky-1]) {
      q[i-ky] = mask;
      } else {
      q[i-ky] = Math.floor((r[i] * radix + r[i-1]) / y[ky-1]);
      }
      for (;;) {
      y2 = (ky > 1 ? y[ky-2] : 0) * q[i-ky];
      c = y2 >> bits_per_element;
      y2 = y2 & mask;
      y1 =c + q[i-ky] * y[ky-1];
      c = y1 >> bits_per_element;
      y1 = y1 & mask;
      if (c == r[i] ? y1 == r[i-1] ? y2>(i > 1 ? r[i-2] : 0) : y1 > r[i-1] : c
              > r[i]) {
        q[i-ky]--;
      } else {
        break;
      }
    }
    linearCombShift(r, y, -q[i-ky], i-ky);
    if (negative(r)) {
      addShift(r, y, i-ky);
      q[i-ky]--;
    }
  }
  rightShift(y, a);
  rightShift(r, a);
}

/*
 *  Performs left shift operation on bigInt by given nu,ber of bits
 *
 *  @param bigInt x  input number
 *  @param integer n  number of bits to be shifted
 */
function leftShift(x, n)
{
  var i;
  var length = Math.floor(n / bits_per_element);
  if (length) {
    for (i = x.length; i >= length; i--){
      x[i] = x[i-length];
    }
    for (;i >= 0; i--) {
      x[i] = 0;
    }  
    n %= bits_per_element;
  }
  if (!n) {
    return;
  }  
  for (i = x.length-1; i > 0; i--) {
  x[i] = mask & ((x[i] << n) | (x[i-1] >> (bits_per_element - n)));
  }
  x[i] = mask & (x[i] << n);
}


/*
 *  Performs right shift operation on bigInt by given number of bits
 *  @param bigInt x  input number
 *  @param integer n  number of bits to be shifted
 */
function rightShift(x, n)
{
  var i;
  var length = Math.floor(n / bits_per_element);
  if (length) {
    for (i = 0; i< x.length - length; i++){ 
      x[i] = x[i + length];
    }
    for (; i< x.length; i++){
      x[i] = 0;
    }
    n %= bits_per_element;
  }
  for (i = 0; i < x.length - 1; i++) {
    x[i] = mask & ((x[i + 1] <<(bits_per_element - n)) | (x[i] >> n));
  }
  x[i] >>= n;
}

/*
 *  To check whether BigInt is negative or not
 *
 *  @return integer output 1 if it is negative otherwise 0
 */
function negative(x)
{
  var result = (x[x.length - 1] >> (bits_per_element - 1)) & 1;
  return result;
}

/*
 *  To perfrom shift operation on y and add it to the x
 *
 *  @param bigInt x  first input number
 *  @param bigInt y  second input number
 *  @return integer output 1 if it is negative otherwise 0
 */
function addShift(x, y, ys)
{
  var i, sum;
  length = x.length < ys + y.length ? x.length : ys + y.length;
  for (c = 0,i = ys; i < length; i++) {
    sum += x[i] + y[i-ys];
    x[i] = sum & mask;
    sum >>= bits_per_element;
  }
  for (i = length;sum && i < x.length; i++) {
    sum += x[i];
    x[i] = sum & mask;
    sum >>= bits_per_element;
  }
}

/*
 *  It right shifts the x by given number of bits and check
 *  whether it is greater than y
 *
 *  @param bigInt x  nonnegative input number
 *  @param bigInt y  nonnegative input number
 *  @param integer shift nonnegative integer
 *  @return integer output 1 if the check passes otherwise returns 0
 */
function greaterShift(x, y, shift)
{
  var i;
  var length = ((x.length + shift) < y.length) ? (x.length + shift) : y.length;
  for (i = y.length - 1 - shift; i < x.length && i >= 0; i++)
  {
    if (x[i] > 0) {
      return 1;
    }
  }
  for (i = x.length - 1 + shift; i < y.length; i++)
  {
    if (y[i] > 0) {
      return 0;
    }
  }
  for (i = length-1; i >= shift; i--)
  {
    if (x[i-shift] > y[i]) {
        return 1;
    } else if (x[i-shift] < y[i]) {
        return 0;
    }
 }
  return 0;
}

/*
 *  To left shift the y by given number of bits and performs
 *  subtraction operation. The result is stored in x
 *
 *  @param bigInt x  BigInt number
 *  @param bigInt y  BigInt number
 *  @param integer shift number of shift bits
 */
function subShift(x, y, ys)
{
  var i, sum;
  var length = x.length < ys + y.length ? x.length : ys + y.length;
  for (c = 0,i = ys; i < length; i++) {
    sum += x[i] - y[i-ys];
    x[i] = sum & mask;
    sum >>= bits_per_element;
  }
  for (i = length; sum && i < x.length; i++) {
    sum += x[i];
    x[i] = sum & mask;
    sum >>= bits_per_element;
  }
}

/*
 *  Wrapper method for the mod operation
 *
 *  @param bigInt x  BigInt number
 *  @param integer n  number
 *  @return result result=x mod n
 */
function mod(x, n)
{
  var ans = dup(x);
  modCalculation(ans, n);
  var result = trim(ans, 1);
  return result;
}

/*
 *  To perform result= x mod n
 *
 *  @param bigInt x  BigInt number
 *  @param integer n  number
 *  @return result result=x mod n
 */
function modCalculation(x, n)
{
  var dividend = new Array(0);
  var divisor = new Array(0);
  if (dividend.length != x.length){
	  dividend = dup(x);
  }else{
    copyBigIntToBigInt(dividend, x);
  }
  if (divisor.length != x.length){
	  divisor = dup(x);
  }
  divide(dividend, n, divisor, x);
}

/*
 *  To return x with exactly k leading zeroes
 *
 *  @param bigInt x  BigInt number
 *  @param integer k  expected number of leading zeroes in x
 *  @return result result=x mod n
 */
function trim(x, k)
{
  var i, y;
  for (i= x.length; i > 0 && !x[i-1]; i--);
  y = new Array(i + k);
  copyBigIntToBigInt(y, x);
  return y;
}

/*
 *  Wrapper method for performing multplication modular opearion
 *
 *  @param bigInt x  BigInt number
 *  @param bigInt y  BigInt number
 *  @param bigInt n  modulus
 *  @return bigInt x*y mod n
 */
function multMod(x, y, n)
{
  var result = expand(x, n.length);
  multModOperation(result, y, n);
  return trim(result, 1);
}

/*
 *  To perform multplication modular opearion
 *
 *  @param bigInt x  BigInt number
 *  @param bigInt y  BigInt number
 *  @param bigInt n  modulus
 *  @return bigInt x * y mod n
 */
 function multModOperation(x, y, n)
 {
  var input_number = new Array(2 * x.length);
  copyBigIntToInt(input_number, 0);
  for (var i=0; i< y.length; i++){
    if (y[i]){
      linearCombShift(input_number, x, y[i], i);
    }
  }
  modCalculation(input_number, n);
  copyBigIntToBigInt(x, input_number);
}

/*
 *  To expand bigInt for the given number of elements.
 *  Leadin zeros are added
 *
 *  @param bigInt x  BigInt number
 *  @param integer n  expected number of elements
 *  @return ans x * y mod n
 */
function expand(x, n)
{
  var ans = int2BigInt(0 ,(x.length > n ? x.length : n) * bits_per_element , 0);
  copyBigIntToBigInt(ans, x);
  return ans;
}