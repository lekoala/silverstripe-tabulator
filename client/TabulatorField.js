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
    var customTickCross = function (cell, formatterParams, onRendered) {
        const tick =
            '<svg width="24" height="24" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13L9 17L19 7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const cross =
            '<svg width="24" height="24" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.75827 17.2426L12.0009 12M17.2435 6.75736L12.0009 12M12.0009 12L6.75827 6.75736M12.0009 12L17.2435 17.2426" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        let el;
        if (cell.getValue()) {
            el = formatterParams.onlyCross ? "" : tick;
        } else {
            el = formatterParams.onlyTick ? "" : cross;
        }
        if (formatterParams.color) {
            el = interpolate('<span style="color:{color}">{el}</span>', {
                color: formatterParams.color,
                el: el,
            });
        }
        return el;
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
        if (formatterParams.showAlt) {
            var showAltClause = formatterParams.showAlt;
            var isNot = showAltClause[0] == "!";
            showAltClause = showAltClause.replace("!", "");
            if (isNot) {
                if (!cell._cell.row.data[showAltClause]) {
                    iconName = formatterParams.showAltIcon;
                }
            } else {
                if (cell._cell.row.data[showAltClause]) {
                    iconName = formatterParams.showAltIcon;
                }
            }
        }

        var iconTitle = formatterParams.title;
        var btnClasses = formatterParams.classes;
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
        options.langs[options.locale].pagination.first =
            '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M18.41 16.59L13.82 12l4.59-4.59L17 6l-6 6 6 6zM6 6h2v12H6z" fill="currentColor"/></svg>';
        options.langs[options.locale].pagination.last =
            '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M5.59 7.41L10.18 12l-4.59 4.59L7 18l6-6-6-6zM16 6h2v12h-2z" fill="currentColor"/></svg>';
        options.langs[options.locale].pagination.next =
            '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/></svg>';
        options.langs[options.locale].pagination.prev =
            '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>';

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
    var publicApi = {
        flagFormatter: flagFormatter,
        buttonFormatter: buttonFormatter,
        customTickCross: customTickCross,
        buttonHandler: buttonHandler,
        init: init,
        dataAjaxResponse: dataAjaxResponse,
    };
    window.SSTabulator = window.SSTabulator
        ? Object.assign(window.SSTabulator, publicApi)
        : publicApi;
})();
