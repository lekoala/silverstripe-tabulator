(function () {
    var buttonFormatter = function (cell, formatterParams, onRendered) {
        var iconName = formatterParams.icon;
        var url = formatterParams.url;

        if (cell._cell.row.data.ID) {
            url += "/" + cell._cell.row.data.ID;
        }
        var icon = '<l-i name="' + iconName + '"></l-i>';
        var link =
            '<a href="' + url + '" class="btn btn-primary">' + icon + "</a>";
        return link;
    };
    var buttonHandler = function (e, cell) {
        console.log("button", e, cell._cell.row.data);
    };
    var init = function (id, options) {
        var table = new Tabulator(id, options);
        table.on("rowClick", function (e, row) {
            var firstBtn = row._row.element.querySelector('.btn');
            if(firstBtn) {
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
