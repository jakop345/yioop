
function createXHR(){
    var request = false;
    try {
        request = new XMLHttpRequest();
    }
    catch (err1) {
        request = false;
    }
    return request;
}

/**
 * Deletes the rows in the table after sending 
 * toolbar data to the Yioop! 
 */
function deleteRows(){
    var file = Components.classes["@mozilla.org/file/directory_service;1"]
        .getService(Components.interfaces.nsIProperties)
        .get("ProfD", Components.interfaces.nsIFile);
    file.append("user_searchcapture.sqlite");

    var storageService = Components.classes["@mozilla.org/storage/service;1"]
        .getService(Components.interfaces.mozIStorageService);
    var mDBConn = storageService.openDatabase(file); 
    // Will also create the file if it does not exist
      
    var statement = mDBConn.createStatement("DELETE FROM search_capture"); 
    statement.executeAsync();
}

/**
 * Makes a legitimate POST request to Yioop!
 * to send toolbar data to the Yioop!
 */
 
function uploadAsyc(url, record){ 
    // url is the script and data is a string of parameters
    params = "c=traffic&a=toolbarTraffic&b=" + record;
    var xhr = createXHR();
    xhr.onreadystatechange=function(){
        if(xhr.readyState == 4)
            {
                // calls deleteRowsfunction on staus Ok
                if(xhr.status == 200){
                    deleteRows();
                }
            }
    };
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.send(params);
}

/**
 * Creates the sqlite database in profiles folder.
 * creates and insers the required captured data from user clicks
 * @ params event is to capture the user click event from linkclick funtion.
 */
  
function getword(event){
    var language1 = content.document.getElementsByTagName("html")[0]
        .getAttribute("lang");
    if(language1 == null){
        var language1 = content.document.getElementsByTagName("html")[0]
            .getAttribute("xml:lang");
    }
    var file = Components.classes["@mozilla.org/file/directory_service;1"]
        .getService(Components.interfaces.nsIProperties)
        .get("ProfD", Components.interfaces.nsIFile);
    file.append("user_searchcapture.sqlite");
           
    var storageService = Components.classes["@mozilla.org/storage/service;1"]
        .getService(Components.interfaces.mozIStorageService);
    var mDBConn = storageService.openDatabase(file); 
    // Will also create the file if it does not exist
                
    mDBConn.executeSimpleSQL("CREATE TABLE IF NOT EXISTS search_capture " +
        "(word TEXT, searchurl TEXT, searchurl1 TEXT, " +
        "timestamp TEXT, language TEXT)");
           
    var stmt = mDBConn.createStatement("INSERT INTO search_capture " +
        "(word, searchurl, searchurl1, timestamp, language) " +
        "VALUES(:word1,:url1,:url2,:time1,:lang1)");
           
    var params = stmt.newBindingParamsArray();
                   
    stmt.params.word1 = event.target.innerHTML;
    stmt.params.url1 = window.content.location.href;
    stmt.params.url2 = event.target.href; 
    stmt.params.time1 = new Date();
    stmt.params.lang1 = language1;
    stmt.executeAsync();

    sendCaptureTest();
    void commitTransaction();
}  

/**
 * Retrieves all the rows from the search_capture table and
 * checks if the rows reached to the count 10 if true
 * then calls the uploadAsync function to send toolbar data to Yioop!
 */

function sendCaptureTest() {
    var yioopurl = "http://www.yioop.com/";
    var file = Components.classes["@mozilla.org/file/directory_service;1"]
        .getService(Components.interfaces.nsIProperties)
        .get("ProfD", Components.interfaces.nsIFile);
    file.append("user_searchcapture.sqlite");

    var storageService = Components.classes["@mozilla.org/storage/service;1"]
        .getService(Components.interfaces.mozIStorageService);
    var mDBConn = storageService.openDatabase(file); 
    // Will also create the file if it does not exist

    var colnew = new Array();
    var statement = mDBConn.createStatement("SELECT * FROM search_capture");

    statement.executeAsync({
        handleResult: function(aResultSet) {
            var i = 0;
            let row = aResultSet.getNextRow();
            for (var row = aResultSet.getNextRow(); row; 
                row = aResultSet.getNextRow()){
                colnew[i] = row.getResultByName("word") + "|:|" 
                + row.getResultByName("searchurl") + "|:|" 
                + row.getResultByName("searchurl1")+ "|:|" 
                + row.getResultByName("timestamp") +  "|:|" 
                + row.getResultByName("language") + "\n";
                ++i;
            }
            if(colnew.length >= 10){ 
                uploadAsyc(yioopurl, colnew);
            }
        },
        handleError: function(aError) {
            alert("Error: " + aError.message);
        },

        handleCompletion: function(aReason) {
            if (aReason != Components.interfaces
                .mozIStorageStatementCallback.REASON_FINISHED)
                alert("Query canceled or aborted!");
        }
    }); 
    commitTransaction();
}

/**
 * The very begining function which is loaded when a Firefox window with the
 * Smart seach toolbar add-on. This stores all the hyperlinks in web page then
 * calls the getword function on the click event i.e when user clciks on a link.
 */
function linkclick() {
    var len = content.document.getElementsByTagName("a");
    for (var i=0; i<len.length; i++) {
        len[i].addEventListener("click", getword, true) //invoke function
    }
}
