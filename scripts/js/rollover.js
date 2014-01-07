/**
 * A new file to house the Rollover JS
 */
/**
 * Calls a proxy script to fix missing enrolments
 */
M.local_rollover = {
    Y : null,
    transaction : [],
    init : function(Y, urls) {
        // Grab a list of Moodles
    	var moodle_urls = Y.JSON.parse(urls);

        // Check auth status on all Moodles
        for (var moodle in moodle_urls) {
        	this.grabData(moodle, moodle_urls[moodle]);
        }
    },
    grabData : function(mdl, url) {
    	this.checkAuth(url, function(result) {
    		if (result === true) {
    			console.log("Authed!");
    		} else {
    			// Retry in 5 seconds
    			setTimeout(function() {
    				this.grabData(mdl, url);
    			}, 5000);
    		}
    	});
    },
    checkAuth : function(url, callback) {
    	// Are we authed with the target installation?
        Y.io(url + "local/rollover/ajax/auth.php", {
            timeout: 1000,
            method: "GET",
            on: {
                success : function (x,o) {
                    // Process the JSON data returned from the server
                    try {
                        data = Y.JSON.parse(o.responseText);
                    }
                    catch (e) {
                        // TODO - error
                        return;
                    }

                    if (data.result == true) {
                        callback(true);
                    } else {
                        callback(data.url);
                    }
                },

                failure : function (x,o) {
                    // TODO - error
                }
            }
        });
    }
}
