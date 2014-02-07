/**
 * A new file to house the Rollover JS
 */
M.local_rollover = {
    Y : null,
    transaction : [],

    /**
     * Init :)
     */
    init : function(Y) {
        var selects = Y.all('.rollover_crs_input');
        selects.each(function(node) {
            node.on('change', function(e) {
                var o = e.currentTarget;
                var frm = o.ancestor();

                var o_item = o.one("option:checked");

                if (o_item.hasAttribute("src_from") && o_item.hasAttribute("src_id")) {
                    frm.one("input[name=id_from]").setAttribute("value", o_item.getAttribute("src_id"));
                    frm.one("input[name=src_from]").setAttribute("value", o_item.getAttribute("src_from"));
                }
            });
        });
    }
}
