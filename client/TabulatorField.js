(() => {
    // Private methods

    const iconTick =
        '<svg width="24" height="24" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 13L9 17L19 7" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    const iconCross =
        '<svg width="24" height="24" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.75827 17.2426L12.0009 12M17.2435 6.75736L12.0009 12M12.0009 12L6.75827 6.75736M12.0009 12L17.2435 17.2426" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    const iconFirst =
        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 24 24"><path d="M18.41 16.59L13.82 12l4.59-4.59L17 6l-6 6 6 6zM6 6h2v12H6z" fill="currentColor"/></svg>';
    const iconLast =
        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 24 24"><path d="M5.59 7.41L10.18 12l-4.59 4.59L7 18l6-6-6-6zM16 6h2v12h-2z" fill="currentColor"/></svg>';
    const iconNext =
        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" fill="currentColor"/></svg>';
    const iconPrev =
        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" fill="currentColor"/></svg>';
    const loader =
        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" width="24px" viewBox="0 0 24 24" xml:space="preserve"><circle fill="currentColor" cx="4" cy="12" r="3"><animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin=".1"/></circle><circle fill="currentColor" cx="12" cy="12" r="3"><animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin=".2"/></circle><circle fill="currentColor" cx="20" cy="12" r="3"><animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin=".3"/></circle></svg>';

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
     * @param {string} msg
     * @param {string} type
     * @param {Tabulator} table
     */
    function notify(msg, type, table = null) {
        if (typeof SSTabulator.notify !== "undefined") {
            SSTabulator.notify(msg, type);
        }
        if (typeof jQuery.noticeAdd !== "undefined") {
            jQuery.noticeAdd({
                text: msg,
                type: type,
                stayTime: 5000,
                inEffect: {
                    left: "0",
                    opacity: "show",
                },
            });
        } else if (typeof window.admini.toaster !== "undefined") {
            window.admini.toaster({
                body: msg,
                className: "border-0 bg-" + type + " text-white",
            });
        } else if (typeof alertify !== "undefined") {
            alertify.notify(result.message, messageType);
        } else if (table) {
            table.alert(msg, type);
        } else {
            console.log(type + " " + msg);
        }
    }

    /**
     * @param {string} handler
     * @returns {Function}
     */
    function getGlobalHandler(handler) {
        if (handler.indexOf(".") !== -1) {
            var parts = handler.split(".");
            namespace = window[parts[0]];
            func = parts[1];
            if (!namespace) {
                console.warn("Undefined namespace", parts[0]);
                return;
            }
            return namespace[func];
        }
        return window[handler];
    }

    /**
     * @returns {string}
     */
    function getSecurityID() {
        var el = document.querySelector("input[name=SecurityID]");
        return el ? el.value : null;
    }

    /**
     * @param {HTMLElement} e
     * @returns {HTMLElement}
     */
    function getInteractiveElement(e) {
        let src = e;
        while (
            !["A", "INPUT", "TD", "TR", "TABLE"].includes(src.tagName) &&
            src.parentElement
        ) {
            src = src.parentElement;
        }
        return src;
    }

    /**
     * @param {string} selector
     * @returns {Promise}
     */
    function waitForElem(selector) {
        return new Promise((resolve) => {
            let el = document.querySelector(selector);
            // let timer;
            if (el) {
                resolve(el);
                return;
            }
            const observer = new MutationObserver((mutations) => {
                for (var i = 0; i < mutations.length; i++) {
                    var mutation = mutations[i];
                    if (mutation.addedNodes.length > 0) {
                        el = document.querySelector(selector);
                        if (el) {
                            // clearTimeout(timer);
                            resolve(el);
                            observer.disconnect();
                        }
                    }
                }
            });

            // timer = setTimeout(() => {
            //     let el = document.querySelector(selector);
            //     if (el) {
            //         resolve(el);
            //         observer.disconnect();
            //     }
            // }, 300);

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
        let el;

        var color = formatterParams.color || null;
        if (cell.getValue()) {
            el = formatterParams.onlyCross ? "" : iconTick;
            color = formatterParams.tickColor || color;
        } else {
            el = formatterParams.onlyTick ? "" : iconCross;
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

        var ajax = formatterParams.ajax || false;
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
        var tag = "a";
        var attrs = "";
        if (ajax) {
            attrs += ' data-ajax="' + ajax + '"';
        }

        if (!url) {
            tag = "span";
        } else {
            url = interpolate(url, cell._cell.row.data);
            if (Object.keys(urlParams).length > 0) {
                url += "?" + new URLSearchParams(urlParams).toString();
            }
            attrs += ' href="' + url + '"';
        }
        var link = '<{tag} class="{classes}"{attrs}>{btnContent}</{tag}>';
        link = interpolate(link, {
            url: url,
            classes: classes,
            btnContent: btnContent,
            attrs: attrs,
            tag: tag,
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

        var btn = cell.getElement().querySelector("a,input,button");
        e.preventDefault();
        if (btn) {
            if (btn.dataset.ajax) {
                e.preventDefault();

                var formData = new FormData();
                formData.append("Action", btn.dataset.action || "");
                formData.append("SecurityID", getSecurityID());
                formData.append("Data", cell.getRow().getData());

                btn.dataset.html = btn.innerHTML;
                btn.innerHTML = loader;

                if (btn.dataset.ajax != 1 && btn.dataset.ajax != "true") {
                    var cb = getGlobalHandler(btn.dataset.ajax);
                    if (!cb) {
                        console.warn("Handler not found", btn.dataset.ajax);
                    } else {
                        cb(e, cell, btn, formData).then(function () {
                            btn.innerHTML = btn.dataset.html;
                        });
                    }
                } else {
                    fetch(btn.getAttribute("href"), {
                        method: "POST",
                        body: formData,
                    }).then(function (response) {
                        if (response.status >= 200 && response.status <= 299) {
                            response.json().then(function (json) {
                                notify(json.message, json.status ?? "success");
                                btn.innerHTML = btn.dataset.html;
                            });
                        } else {
                            response.text().then(function (message) {
                                notify(message, "bad");
                                btn.innerHTML = btn.dataset.html;
                            });
                        }
                    });
                }
            }
        }
    };
    var pendingInit = {};
    var initElement = function (el, options) {
        let selector = "#" + el.getAttribute("id");
        pendingInit[selector] = false;
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
        if (pendingInit[selector]) {
            console.warn("Init is already pending for this element", selector);
            return;
        }
        pendingInit[selector] = true;
        waitForElem(selector).then((el) => {
            if (el.classList.contains("tabulatorgrid-created")) {
                el.remove();
                // It's already there from a previous request and content hasn't been refreshed yet
                waitForElem(selector).then((el) => {
                    initElement(el, options);
                    // Trigger lazy load if visible
                    if (!isHidden(el)) {
                        el.dispatchEvent(new Event("lazyload"));
                    }
                });
            } else {
                initElement(el, options);
            }
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
    var disableCallback = false;
    var cellEditedCallback = function (cell) {
        if (disableCallback) {
            return;
        }
        var value = cell.getValue();
        var column = cell.getColumn().getField();
        var data = cell.getRow().getData();
        var editUrl = cell.getTable().element.dataset.editUrl;
        if (!editUrl) {
            return;
        }

        disableCallback = true;
        editUrl = interpolate(editUrl, data);

        var formData = new FormData();
        formData.append("Column", column);
        formData.append("Value", value);
        formData.append("SecurityID", getSecurityID());
        formData.append("Data", data);

        if (column.indexOf(".") !== -1) {
            cell.setValue("");
        }

        fetch(editUrl, {
            method: "POST",
            body: formData,
        }).then(function (response) {
            if (response.status >= 200 && response.status <= 299) {
                response.json().then(function (json) {
                    notify(json.message, json.status ?? "success");

                    if (json.value && json.value != value) {
                        cell.setValue(json.value);
                    } else if (!cell.getValue() && value) {
                        cell.setValue(value);
                    }
                    disableCallback = false;
                });
            } else {
                response.text().then(function (message) {
                    notify(message, "bad");
                    disableCallback = false;
                });
            }
        });
    };
    var forwardClick = function (e, cell) {
        e.preventDefault();
        e.stopPropagation();
        var input = cell.getElement().querySelector("input");
        if (input && !input.readonly && !input.disabled) {
            input.checked = !input.checked;
        }
    };
    /**
     * @param {Tabulator} table
     * @param {string} group
     * @returns {GroupRows}
     */
    var getGroupByKey = function (table, group) {
        var groups = table.modules.groupRows.getGroups();
        var s = null;
        groups.forEach((g) => {
            if (g.key == group) {
                s = g;
            }
        });
        return s;
    };
    var createTabulator = function (selector, options) {
        let el = document.querySelector(selector);
        if (el.classList.contains("tabulatorgrid-created")) {
            return;
        }
        let dataset = el.dataset;
        el.classList.add("tabulatorgrid-created");

        if (dataset.useCustomPaginationIcons) {
            options.langs[options.locale].pagination.first = iconFirst;
            options.langs[options.locale].pagination.last = iconLast;
            options.langs[options.locale].pagination.next = iconNext;
            options.langs[options.locale].pagination.prev = iconPrev;
        }

        var tabulator = new Tabulator(selector, options);

        // Add desktop or mobile class
        let navigatorClass = "desktop";
        if (tabulator.browserMobile) {
            navigatorClass = "mobile";
        }

        el.classList.add("tabulator-navigator-" + navigatorClass);

        // Register events
        const listeners = JSON.parse(dataset.listeners);
        for (const listenerName in listeners) {
            var cb = getGlobalHandler(listeners[listenerName]);
            if (cb) {
                tabulator.on(listenerName, cb);
            } else {
                console.warn(
                    "Listener not found for " + listenerName,
                    listeners[listenerName]
                );
            }
        }
        // Default edit callback
        if (!listeners["cellEdited"]) {
            tabulator.on("cellEdited", cellEditedCallback);
        }

        // Trigger first action on row click if present
        if (dataset.rowClickTriggersAction) {
            tabulator.on("rowClick", function (e, row) {
                if (e.target.classList.contains("tabulator-cell-editable")) {
                    return;
                }
                if (e.target.classList.contains("tabulator-cell-btn")) {
                    return;
                }
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
        forwardClick: forwardClick,
        moneyEditor: moneyEditor,
        externalEditor: externalEditor,
        isCellEditable: isCellEditable,
        getGroupByKey: getGroupByKey,
        init: init,
    };

    // You can extend this with your own features
    window.SSTabulator = window.SSTabulator
        ? Object.assign(window.SSTabulator, publicApi)
        : publicApi;
})();
