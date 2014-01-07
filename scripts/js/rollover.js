/**
 * A new file to house the Rollover JS
 */
/**
 * Calls a proxy script to fix missing enrolments
 */
M.local_rollover = {
    Y : null,
    transaction : [],
    init : function(Y) {
        // Grab a list of Moodles
    },
    checkAuth: function(mdl, callback) {
    	// Are we authed with the target installation?
        Y.io(mdl + "/local/rollover/ajax/auth.php", {
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
