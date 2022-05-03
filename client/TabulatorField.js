(() => {
    function getInteractiveElement(e) {
        let src = e;
        while (
            !["A", "INPUT", "TD"].includes(src.tagName) &&
            src.parentElement
        ) {
            src = src.parentElement;
        }
        return src;
    }

    var interpolate = (str, data) => {
        return str.replace(/\{([^\}]+)?\}/g, function ($1, $2) {
            return data[$2];
        });
    };
    var flagFormatter = function (cell, formatterParams, onRendered) {
        if (!cell.getValue()) {
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
        var iconTitle = formatterParams.title;
        var btnClasses = formatterPamras.classes;
        var url = formatterParams.url;
        url = interpolate(url, cell._cell.row.data);

        var classes = btnClasses || "btn btn-primary";
        // It can be an url
        if (iconName[0] === "/") {
            var icon = '<img src="' + iconName + '" alt="' + iconTitle + '"/>';
        } else {
            var icon = '<l-i name="' + iconName + '"></l-i>';
            if (typeof LastIcon == "undefined") {
                icon = '<span class="font-icon-' + iconName + '"></span>';
            }
        }

        var link =
            '<a href="' + url + '" class="' + classes + '">' + icon + "</a>";
        return link;
    };
    var buttonHandler = function (e, cell) {
        console.log("button", e, cell._cell.row.data);
    };
    var init = function (selector, options) {
        const el = document.querySelector(selector);
        if (el.classList.contains("lazy-loadable")) {
            el.addEventListener(
                "lazyloaded",
                (e) => {
                    createTabulator(selector, options);
                },
                { once: true }
            );
        } else {
            createTabulator(selector, options);
        }
    };
    var dataAjaxResponse = function (url, params, response) {
        if (!response.data) {
            console.error("Response does not contain a data key");
        }
        return response.data;
    };
    var createTabulator = function (selector, options) {
        // Enable last icon pagination (material icons)
        if (typeof LastIcon != "undefined") {
            options.langs[options.locale].pagination =
                options.langs[options.locale].pagination || {};
            options.langs[options.locale].pagination.first =
                '<l-i name="first_page"></l-i>';
            options.langs[options.locale].pagination.last =
                '<l-i name="last_page"></l-i>';
            options.langs[options.locale].pagination.next =
                '<l-i name="navigate_next"></l-i>';
            options.langs[options.locale].pagination.prev =
                '<l-i name="navigate_before"></l-i>';
        }

        var tabulator = new Tabulator(selector, options);

        // Trigger first action on row click if present
        tabulator.on("rowClick", function (e, row) {
            let target = getInteractiveElement(e.target);
            if (["A", "INPUT"].includes(target.tagName)) {
                return;
            }
            var firstBtn = row._row.element.querySelector(".btn");
            if (firstBtn) {
                firstBtn.click();
            }
        });

        // Mitigate issue https://github.com/olifolkerd/tabulator/issues/3692
        document
            .querySelector(selector)
            .addEventListener("keydown", function (e) {
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
        dataAjaxResponse: dataAjaxResponse,
    };
})();
