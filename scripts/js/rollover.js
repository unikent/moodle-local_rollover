/**
 * A new file to house the Rollover JS
 */
/**
 * Calls a proxy script to fix missing enrolments
 */
M.local_rollover = {
    Y : null,
    transaction : [],

    /**
     * Init :)
     */
    init : function(Y, urls) {

        var loadingPanel = new M.core.dialogue({
            headerContent: "Please Wait",
            bodyContent: "Loading data...",
            visible: false,
            lightbox: true,
            zIndex: 100,
            closeButtonTitle: "Close"
        });
        loadingPanel.centerDialogue();
        loadingPanel.show();

        // Grab a list of Moodles
    	var moodle_urls = Y.JSON.parse(urls);

        // Check auth status on all Moodles
        for (var moodle in moodle_urls) {
        	this.grabData(moodle, moodle_urls[moodle], loadingPanel);
        }
    },

    /**
     * Grab course listings from the target Moodle
     */
    grabData : function(mdl, url, panel) {
    	var self = this;

    	this.checkAuth(url, function(result) {
    		if (result === true) {
    			var item = Y.one("#auth-status-" + mdl);
    			if (item) {
    				item.remove();
    			}
    		} else {
    			var auth_url = result;
    			if (panel) {
					panel.get('contentBox').append("<p id=\"auth-status-" + mdl + "\">Please auth with " + mdl + " (<a href=\"" + url + "\">click here</a>)</p>");
	    		}

    			// Retry in 5 seconds
    			setTimeout(function() {
    				self.grabData(mdl, url, false);
    			}, 5000);
    		}
    	});
    },

    /**
     * Check if we are authenticated on the target Moodle
     */
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
