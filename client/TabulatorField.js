(function () {
    var interpolate = function (str, data) {
        return str.replace(/\{([^\}]+)?\}/g, function ($1, $2) {
            return data[$2];
        });
    };
    var flagFormatter = function (cell, formatterParams, onRendered) {
        if(!cell.getValue()) {
            return;
        }
        var iconName = cell.getValue().toLowerCase();
        if (typeof LastIcon == "undefined") {
            return iconName;
        }
        var icon = '<l-i name="' + iconName + '" set="fl"></l-i>';
        return icon;
    };
    var buttonFormatter = function (cell, formatterParams, onRendered) {
        var iconName = formatterParams.icon;
        var url = formatterParams.url;
        url = interpolate(url, cell._cell.row.data);

        var classes = "btn btn-primary";
        var icon = '<l-i name="' + iconName + '"></l-i>';
        if (typeof LastIcon == "undefined") {
            icon = '<span class="font-icon-' + iconName + '"></span>';
        }
        var link =
            '<a href="' + url + '" class="' + classes + '">' + icon + "</a>";
        return link;
    };
    var buttonHandler = function (e, cell) {
        console.log("button", e, cell._cell.row.data);
    };
    var init = function (id, options) {
        // Enable last icon pagination
        if (typeof LastIcon != "undefined") {
            options.langs[options.locale].pagination = options.langs[options.locale].pagination || {};
            options.langs[options.locale].pagination.first = '<l-i name="first_page"></l-i>';
            options.langs[options.locale].pagination.last = '<l-i name="last_page"></l-i>';
            options.langs[options.locale].pagination.next = '<l-i name="navigate_next"></l-i>';
            options.langs[options.locale].pagination.prev =
                '<l-i name="navigate_before"></l-i>';
        }

        var tabulator = new Tabulator(id, options);
        tabulator.on("rowClick", function (e, row) {
            var firstBtn = row._row.element.querySelector(".btn");
            if (firstBtn) {
                firstBtn.click();
            }
        });

        // Mitigate issue https://github.com/olifolkerd/tabulator/issues/3692
        document.querySelector(id).addEventListener("keydown", function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
            }
        });
    };

    // Public api
    window.SSTabulator = {
        flagFormatter: flagFormatter,
        buttonFormatter: buttonFormatter,
        buttonHandler: buttonHandler,
        init: init,
    };
})();
