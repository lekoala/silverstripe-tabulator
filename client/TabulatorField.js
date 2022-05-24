(() => {
    // Private methods

    /**
     * @param {number} n
     * @returns {boolean}
     */
    function isNumeric(n) {
        return !isNaN(parseFloat(n)) && isFinite(n);
    }

    /**
     * @param {HTMLElement} el
     * @returns {boolean}
     */
    function isHidden(el) {
        return el.offsetHeight <= 0 && el.offsetWidth <= 0;
    }

    /**
     * @param {HTMLElement} e
     * @returns {HTMLElement}
     */
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

    /**
     * @param {string} selector
     * @param {fromMutation} alreadyPresent
     * @returns {Promise}
     */
    function waitForElem(selector, alreadyPresent = true) {
        return new Promise((resolve) => {
            if (alreadyPresent) {
                let el = document.querySelector(selector);
                if (el) {
                    return resolve(el);
                }
            }
            const observer = new MutationObserver((mutations) => {
                for (var i = 0; i < mutations.length; i++) {
                    var mutation = mutations[i];
                    if (mutation.addedNodes.length > 0) {
                        el = document.querySelector(selector);
                        if (el) {
                            resolve(el);
                            observer.disconnect();
                        }
                    }
                }
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });
        });
    }

    /**
     * @typedef {Object} MoneyResult
     * @property {string} input - Source string
     * @property {string} locale - Locale used
     * @property {string} currency - Currency used
     * @property {boolean} isValid - Is valid input
     * @property {string} string - String using fixed point notation
     * @property {Number} number - Number instance with format
     * @property {string} output - Formatted output
     */

    /**
     * Parse value to currency
     * @param {number|string} input - Given input
     * @param {string} locale - Desired locale i.e: "en-US" "hr-HR"
     * @param {string} currency - Currency to use "USD" "EUR" "HRK"
     * @return {MoneyResult} - Formatting results
     */
    function parseMoney(input, locale = "en-US", currency = "USD") {
        let fmt = String(input)
            .replace(/(?<=\d)[.,](?!\d+$)/g, "")
            .replace(",", ".");
        const pts = fmt.split(".");
        if (pts.length > 1) {
            if (+pts[0] === 0) fmt = pts.join(".");
            else if (pts[1].length === 3) fmt = pts.join("");
        }
        const number = Number(fmt);
        const isValid = isFinite(number);
        const string = number.toFixed(2);
        const intlNFOpts = new Intl.NumberFormat(locale, {
            style: "currency",
            currency: currency,
        }).resolvedOptions();
        const output = number.toLocaleString(locale, {
            ...intlNFOpts,
            style: "decimal",
        });
        return {
            input,
            locale,
            currency,
            isValid,
            string,
            number,
            output,
        };
    }

    function interpolate(str, data) {
        return str.replace(/\{([^\}]+)?\}/g, function ($1, $2) {
            return data[$2];
        });
    }

    // Public methods

    var customTickCrossFormatter = function (
        cell,
        formatterParams,
        onRendered
    ) {
        const tick =
            '<svg width="24" height="24" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13L9 17L19 7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        const cross =
            '<svg width="24" height="24" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.75827 17.2426L12.0009 12M17.2435 6.75736L12.0009 12M12.0009 12L6.75827 6.75736M12.0009 12L17.2435 17.2426" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        let el;

        var color = formatterParams.color || null;
        if (cell.getValue()) {
            el = formatterParams.onlyCross ? "" : tick;
            color = formatterParams.tickColor || color;
        } else {
            el = formatterParams.onlyTick ? "" : cross;
            color = formatterParams.crossColor || color;
        }
        if (color) {
            el = interpolate('<span style="color:{color}">{el}</span>', {
                color: color,
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
        // We can show alternative icons based on simple state on the row
        if (formatterParams.showAlt) {
            var showAltField = formatterParams.showAlt;
            var isNot = showAltField[0] == "!";
            showAltField = showAltField.replace("!", "");
            var altValue = cell._cell.row.data[showAltField];
            if (typeof altValue == "undefined") {
                return "";
            }
            if (isNot) {
                if (!altValue) {
                    iconName = formatterParams.showAltIcon;
                }
            } else {
                if (altValue) {
                    iconName = formatterParams.showAltIcon;
                }
            }
        }

        var title = formatterParams.title;
        var btnClasses = formatterParams.classes;
        var showIconTitle = formatterParams.showIconTitle;
        var urlParams = formatterParams.urlParams || {};
        var classes = btnClasses || "btn btn-primary";
        var icon = "";
        var btnContent = title;
        if (iconName) {
            // It can be an url or an icon name
            if (iconName[0] === "/") {
                icon = '<img src="' + iconName + '" alt="' + title + '"/>';
            } else {
                icon = '<l-i name="' + iconName + '"></l-i>';
                if (typeof LastIcon == "undefined") {
                    icon = '<span class="font-icon-' + iconName + '"></span>';
                }
            }
            if (showIconTitle) {
                btnContent = icon + btnContent;
            } else {
                btnContent = icon;
            }
        }
        var url = formatterParams.url;
        if (!url) {
            return btnContent;
        }
        url = interpolate(url, cell._cell.row.data);
        url += "?" + new URLSearchParams(urlParams).toString();
        var link = '<a href="{url}" class="{classes}">{btnContent}</a>';
        link = interpolate(link, {
            url: url,
            classes: classes,
            btnContent: btnContent,
        });
        return link;
    };
    var externalFormatter = function (cell, formatterParams, onRendered) {
        var v = cell.getValue();
        var formatted = "";
        if (v || formatterParams["notNull"]) {
            formatted = window[formatterParams["function"]](v);
        }
        return formatted;
    };
    var buttonHandler = function (e, cell) {
        // This helps restoring state after click on button
        document.cookie = "hash=" + (window.location.hash || "") + "; path=/";
    };
    var initElement = function (el, options) {
        let selector = "#" + el.getAttribute("id");
        if (el.classList.contains("lazy-loadable")) {
            el.addEventListener(
                "lazyload",
                (e) => {
                    if (!isHidden(el)) {
                        createTabulator(selector, options);
                    }
                },
                {
                    once: true,
                }
            );
        } else {
            createTabulator(selector, options);
        }
    };
    var init = function (selector, options) {
        waitForElem(selector).then((el) => {
            if (el.classList.contains("tabulatorgrid-created")) {
                // It's already there from a previous request and content hasn't been refreshed yet
                waitForElem(selector, false).then((el) => {
                    initElement(el, options);
                });
                return;
            }
            initElement(el, options);
        });
    };
    var dataAjaxResponse = function (url, params, response) {
        if (!response.data) {
            console.error("Response does not contain a data key");
        }
        return response.data;
    };
    var simpleRowFormatter = function (row) {
        const data = row.getData();
        if (data._color) {
            row.getElement().style.backgroundColor = data._color;
        }
        if (data._class) {
            row.getElement().classList.add(data._class);
        }
    };
    var boolGroupHeader = function (value, count, data, group) {
        if (value) {
            return group._group.field + " (" + count + ")";
        }
        return "(" + count + ")";
    };
    var expandTooltip = function (e, cell, onRendered) {
        const el = cell._cell.element;
        const isTruncated = el.scrollWidth > el.clientWidth;
        if (isTruncated) {
            return cell._cell.value;
        }
        return "";
    };
    var isCellEditable = function (cell) {
        return cell._cell.row.data["_editable"];
    };
    var dateEditor = function (
        cell,
        onRendered,
        success,
        cancel,
        editorParams
    ) {
        //create and style editor
        var editor = document.createElement("input");

        editor.setAttribute("type", "date");

        //create and style input
        editor.style.padding = "4px";
        editor.style.width = "100%";
        editor.style.boxSizing = "border-box";

        //Set value of editor to the current value of the cell
        editor.value = luxon.DateTime.fromFormat(
            cell.getValue(),
            "dd/MM/yyyy"
        ).toFormat("yyyy-MM-dd");

        //set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
        onRendered(function () {
            editor.focus({ preventScroll: true });
            editor.style.height = "100%";
        });

        //when the value has been set, trigger the cell to update
        function successFunc() {
            success(
                luxon.DateTime.fromFormat(editor.value, "yyyy-MM-dd").toFormat(
                    "dd/MM/yyyy"
                )
            );
        }

        editor.addEventListener("change", successFunc);
        editor.addEventListener("blur", successFunc);

        //return the editor element
        return editor;
    };
    var moneyEditor = function (
        cell,
        onRendered,
        success,
        cancel,
        editorParams
    ) {
        //create and style editor
        var editor = document.createElement("input");

        editor.setAttribute("type", "text");

        //create and style input
        editor.style.padding = "4px";
        editor.style.width = "100%";
        editor.style.boxSizing = "border-box";

        //Set value of editor to the current value of the cell
        editor.value = cell.getValue() ?? "";

        //set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
        onRendered(function () {
            editor.focus({ preventScroll: true });
            editor.style.height = "100%";
            if (editorParams.selectContents) {
                editor.select();
            }
        });

        //when the value has been set, trigger the cell to update
        function successFunc() {
            editor.value = editor.value.trim();
            if (editor.value || editorParams.notNull) {
                let fmt = parseMoney(editor.value);
                editor.value = fmt.output;
            }
            success(editor.value);
        }

        editor.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                successFunc();
            }
            if (e.key === "Escape") {
                e.preventDefault();
                cancel();
            }
            if (
                e.key.length === 1 &&
                !(isNumeric(e.key) || [".", ","].includes(e.key))
            ) {
                e.preventDefault();
            }
        });

        editor.addEventListener("change", successFunc);
        editor.addEventListener("blur", successFunc);

        //return the editor element
        return editor;
    };
    var externalEditor = function (
        cell,
        onRendered,
        success,
        cancel,
        editorParams
    ) {
        //create and style editor
        var tagType = editorParams.tagType || "input";
        var editor = document.createElement(tagType);
        if (tagType === "input") {
            editor.setAttribute("type", "text");
        }

        var uid =
            Date.now().toString(36) + Math.random().toString(36).substring(2);

        //create and style tag
        editor.style.padding = "4px";
        editor.style.width = "100%";
        editor.style.boxSizing = "border-box";
        editor.setAttribute("id", "tabulator-editor-" + uid);

        //Set value of editor to the current value of the cell
        editor.value = cell.getValue() ?? "";
        editor.dataset.prevValue = editor.value;

        //set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
        onRendered(function () {
            editor.focus({ preventScroll: true });
            editor.style.height = "100%";

            // init external editor
            var el = editorParams.idSelector
                ? "#" + editor.getAttribute("id")
                : editor;
            var opts = editorParams.options || {};
            var inst = window[editorParams.function](el, opts);
        });

        //when the value has been set, trigger the cell to update
        function successFunc() {
            editor.value = editor.value.trim();
            if (editor.value == editor.dataset.prevValue) {
                cancel();
                return;
            }
            if (editor.value || editorParams.notNull) {
                let fmt = parseMoney(editor.value);
                editor.value = fmt.output;
            }
            success(editor.value);
        }

        editor.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                successFunc();
            }
            if (e.key === "Escape") {
                e.preventDefault();
                cancel();
            }
            if (
                e.key.length === 1 &&
                !(isNumeric(e.key) || [".", ","].includes(e.key))
            ) {
                e.preventDefault();
            }
        });

        editor.addEventListener("change", successFunc);
        editor.addEventListener("blur", successFunc);

        //return the editor element
        return editor;
    };
    var createTabulator = function (selector, options) {
        let el = document.querySelector(selector);
        if (el.classList.contains("tabulatorgrid-created")) {
            return;
        }
        el.classList.add("tabulatorgrid-created");
        let listeners = {};
        if (typeof options.listeners != "undefined") {
            listeners = options.listeners;
            delete options["listeners"];
        }
        let useCustomPaginationIcons = false;
        if (typeof options.useCustomPaginationIcons != "undefined") {
            useCustomPaginationIcons = options.useCustomPaginationIcons;
            delete options["useCustomPaginationIcons"];
        }
        if (useCustomPaginationIcons) {
            options.langs[options.locale].pagination.first =
                '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M18.41 16.59L13.82 12l4.59-4.59L17 6l-6 6 6 6zM6 6h2v12H6z" fill="currentColor"/></svg>';
            options.langs[options.locale].pagination.last =
                '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M5.59 7.41L10.18 12l-4.59 4.59L7 18l6-6-6-6zM16 6h2v12h-2z" fill="currentColor"/></svg>';
            options.langs[options.locale].pagination.next =
                '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/></svg>';
            options.langs[options.locale].pagination.prev =
                '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>';
            delete options["useCustomPaginationIcons"];
        }
        let rowClickTriggersAction = false;
        if (typeof options.rowClickTriggersAction != "undefined") {
            rowClickTriggersAction = options.rowClickTriggersAction;
            delete options["rowClickTriggersAction"];
        }

        var tabulator = new Tabulator(selector, options);

        // Add desktop or mobile class
        let navigatorClass = "desktop";
        if (tabulator.browserMobile) {
            navigatorClass = "mobile";
        }

        el.classList.add("tabulator-navigator-" + navigatorClass);

        // Register events
        for (const listenerName in listeners) {
            var cb = listeners[listenerName];
            tabulator.on(listenerName, cb);
        }

        // Trigger first action on row click if present
        if (rowClickTriggersAction) {
            tabulator.on("rowClick", function (e, row) {
                let target = getInteractiveElement(e.target);
                if (["A", "INPUT"].includes(target.tagName)) {
                    return;
                }
                var firstBtn = null;
                firstBtn = row._row.element.querySelector(
                    ".btn.default-action"
                );
                if (!firstBtn) {
                    firstBtn = row._row.element.querySelector(".btn");
                }
                if (firstBtn) {
                    firstBtn.click();
                }
            });
        }

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
        customTickCrossFormatter: customTickCrossFormatter,
        externalFormatter: externalFormatter,
        buttonHandler: buttonHandler,
        boolGroupHeader: boolGroupHeader,
        simpleRowFormatter: simpleRowFormatter,
        expandTooltip: expandTooltip,
        dataAjaxResponse: dataAjaxResponse,
        dateEditor: dateEditor,
        moneyEditor: moneyEditor,
        externalEditor: externalEditor,
        isCellEditable: isCellEditable,
        init: init,
    };

    // You can extend this with your own features
    window.SSTabulator = window.SSTabulator
        ? Object.assign(window.SSTabulator, publicApi)
        : publicApi;
})();
