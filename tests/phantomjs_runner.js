var page = require('webpage').create(),
    testindex = 0,
    filename,
    loadInProgress = false,
    fs = require('fs'),
    path = 'results.json',
    results = {},
    system = require('system'),
    args = system.args,
    DEBUG = false,
    page_url = args[1],
    mode = args[2],
    yioop_username = "root",
    yioop_password = "";
//Check is debug mode is intended.
if(args[3] === "true") {
    DEBUG = true;
}
/**
 * set viewport for debugging using slimerjs.
 */
page.viewportSize = {
    width: 1440,
    height: 768
};
/**
 * Prototype JS Array to add a "has" method that checks if the array has
 * the element passed as param.
 * @param v element to be tested against an array of elements.
 * @returns boolean exists or not.
 */
Array.prototype.has = function (v)
{
    for (i = 0; i < this.length; i++) {
        if(this[i] == v) return true;
    }
    return false;
};
var supported_tests = ["web", "mobile"];
if(supported_tests.has(mode)) {
    filename = mode + "_ui_tests.js",
        phantom.injectJs(filename);
} else {
    console.log("Invalid test case. Terminating PhantomJS.");
    phantom.exit();
}
/**
 * Helper Functions for running PhantomJS tests.
 */
/**
 * utility method which prints message to console if debug var is true.
 * @param String msg to be printed to console.
 */
function l(msg)
{
    !DEBUG || console.log("[Debug] : " + msg);
}
/**
 * Returns the name of the function when a  function is passed.
 * @param Function fun whose name to be extracted.
 * @returns String ret function name.
 */
function functionName(fun)
{
    var ret = fun.toString();
    ret = ret.substr('function '.length);
    ret = ret.substr(0, ret.indexOf('('));
    return ret;
}
/**
 * A function thet converts Object to String and prints the result to
 * console.
 */
function renderTestResults()
{
    console.log(JSON.stringify(results));
}
/**
 * PhantomJs Tests setup.
 **/
/**
 * On encountering a console message in the page being evaluated,
 * redirect to PhantomJS console.
 * @param Strine msg caught from console.
 */
page.onConsoleMessage = function (msg)
{
    console.log(msg);
};
/**
 * set the flag variable loadInProgress to true when load is in progress.
 */
page.onLoadStarted = function ()
{
    loadInProgress = true;
};
/**
 * set the flag variable loadInProgress to false when load is finished.
 */
page.onLoadFinished = function ()
{
    loadInProgress = false;
};
/**
 * This method clicks on a an element.
 * @param String selector CSS selector of the element to be clicked on.
 */
page.click = function (selector)
{
    l("Clicking Element[ " + selector + "]");
    this.evaluate(function (selector, l)
    {
        var e = document.createEvent("MouseEvents");
        e.initEvent("click", true, true);
        document.querySelector(selector).dispatchEvent(e);
    }, selector, l);
};
/**
 * This function takes in a CSS selector a description message. The page in
 * context is searched for the css selector, and if element exists, a result
 * object with ack= true , status= PASS and the reference to element along with
 * the message are constructed and returned.
 *
 * @param String selector CSS selector for the element being tested.
 * @param String message description of test
 * @returns object res
 */
page.assertExists = function (selector, message)
{
    var res = {};
    res.msg = message;
    res.elm = null;
    res.elm = this.evaluate(function (selector)
    {
        return document.querySelector(selector);
    }, selector);
    if(res.elm) {
        res.status = "PASS";
        res.ack = true;
    } else {
        res.status = "FAIL";
        res.ack = false;
    }
    return res;
};
/**
 * This function runs each step periodically delayed by 2 seconds.
 * each function returns a result object with
 * elm-DOM reference to the
 * element on page which is being tested.
 * ack - test passed true/false
 * status - readable PASS or FAIL.
 * msg - more like a description of test.
 * the element is used by the test function, but then stripped off by this
 * function before adding to result collection - results.
 * Once all steps are completed, results are printed to console, followed by
 * exiting PhantomJS.
 */
interval = setInterval(function ()
{
    if(!loadInProgress && typeof steps[testindex] == "function") {
        var func = steps[testindex];
        var result = steps[testindex]();
        var function_name = functionName(func);
        l("Test #" + (testindex + 1) + ": " + function_name);
        if(result) {
            l(result.status + " - " + result.msg);
            delete result.elm;
            results[function_name] = (result);
        }
        testindex++;
    }
    if(typeof steps[testindex] != "function") {
        l("All Tests complete!");
        renderTestResults();
        phantom.exit();
    }
}, 4000);