(function () {
    var interpolate = function (str, data) {
        return str.replace(/\{([^\}]+)?\}/g, function ($1, $2) {
            return data[$2];
        });
    };

    var buttonFormatter = function (cell, formatterParams, onRendered) {
        var iconName = formatterParams.icon;
        var url = formatterParams.url;
        url = interpolate(url, cell._cell.row.data);

        var classes = "btn btn-primary";
        var icon = '<l-i name="' + iconName + '"></l-i>';
        var link =
            '<a href="' + url + '" class="' + classes + '">' + icon + "</a>";
        return link;
    };
    var buttonHandler = function (e, cell) {
        console.log("button", e, cell._cell.row.data);
    };
    var init = function (id, options) {
        var table = new Tabulator(id, options);
        table.on("rowClick", function (e, row) {
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
        buttonFormatter: buttonFormatter,
        buttonHandler: buttonHandler,
        init: init,
    };
})();
