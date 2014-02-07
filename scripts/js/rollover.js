/**
 * A new file to house the Rollover JS
 *
 *
 * **********ALL DEPRECATED************
 * 
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
    init : function(Y) {

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

        this.grabData(loadingPanel);
    },

    /**
     * Grab course listings from the target Moodle
     */
    grabData : function(loadingPanel) {
    	var self = this;


        Y.io(M.cfg.wwwroot + "/local/connect/ajax/rollover_sources.php", {
            timeout: 30000,
            method: "GET",
            on: {
                success : function (x,o) {
                    // Process the JSON data returned from the server
                    try {
                        data = Y.JSON.parse(o.responseText);
                    }
                    catch (e) {
                        alert("Something went wrong! Please try again later.");
                        return;
                    }

                    console.log(data.targets);
                    console.log(data.sources);

                    loadingPanel.hide();
                },

                failure : function (x,o) {
                    alert("Something went wrong! Please try again later.");
                }
            }
        });
    }
}
