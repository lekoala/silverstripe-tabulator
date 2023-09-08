(() => {
    // The SMIL specification says that durations cannot start with a leading decimal point.
    // Firefox implements the specification as written, Chrome does not. Converting from dur=".75s" to dur="0.75s" will fix it in a cross-browser fashion.
    const loader =
        '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" xml:space="preserve"><circle fill="currentColor" cx="4" cy="12" r="3"><animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin="0.1"/></circle><circle fill="currentColor" cx="12" cy="12" r="3"><animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin="0.2"/></circle><circle fill="currentColor" cx="20" cy="12" r="3"><animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin="0.3"/></circle></svg>';

    // helper functions

    /**
     * @param {string} str
     * @param {Object} data
     */
    function interpolate(str, data) {
        return str.replace(/\{([^\}]+)?\}/g, ($1, $2) => {
            return data[$2] || "";
        });
    }

    function getGlobalFn(fn) {
        if (typeof fn == "function") {
            return fn;
        }
        return fn.split(".").reduce((r, p) => r[p], window);
    }

    function debounce(func, timeout = 300) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                func.apply(this, args);
            }, timeout);
        };
    }

    function defaultActionHandler(json, table) {
        if (json.reload) {
            table.setData();
        }
        if (json.refresh) {
            window.location.reload();
        }
    }

    /**
     * @param {HTMLElement} btn
     * @param {string} endpoint
     * @param {FormData} formData
     * @param {Function} cb
     */
    function handleAction(btn, endpoint, formData, cb = null) {
        if (!btn.dataset.html) {
            btn.dataset.html = btn.innerHTML;
        }
        btn.innerHTML = loader;

        fetchWrapper(endpoint, {
            method: "POST",
            body: formData,
        })
            .then((json) => {
                notify(json.message, json.status || "success");
                btn.innerHTML = btn.dataset.html;
                if (cb) {
                    cb(json);
                }
            })
            .catch((message) => {
                notify(message, "bad");
                btn.innerHTML = btn.dataset.html;
            });
    }

    function fetchWrapper(url, options = {}) {
        // Legacy server compat
        options.headers = options.headers || {};
        options.headers["X-Requested-With"] = "XMLHttpRequest";

        return fetch(url, options).then((response) => {
            return response.text().then((text) => {
                // Make sure we have JSON content
                const data =
                    text && ["[", "{"].includes(text[0]) && JSON.parse(text);

                if (!response.ok) {
                    // Use json error message if any or HTTP status text
                    const error = (data && data.message) || response.statusText;
                    return Promise.reject(error);
                }

                return data;
            });
        });
    }

    /**
     * @param {string} msg
     * @param {string} type
     * @param {Tabulator} table
     */
    function notify(msg, type, table = null) {
        if (!type) {
            type = "info";
        }
        if (typeof SSTabulator.notify !== "undefined") {
            SSTabulator.notify(msg, type);
        } else if (
            typeof jQuery !== "undefined" &&
            typeof jQuery.noticeAdd !== "undefined"
        ) {
            jQuery.noticeAdd({
                text: msg,
                type: type,
                stayTime: 5000,
                inEffect: {
                    left: "0",
                    opacity: "show",
                },
            });
        } else if (
            typeof window.admini.toaster !== "undefined" ||
            typeof window.toaster !== "undefined"
        ) {
            type = type == "bad" ? "danger" : type;
            const toaster = window.admini.toaster || window.toaster;
            toaster({
                body: msg,
                className: "border-0 bg-" + type + " text-white",
            });
        } else if (typeof alertify !== "undefined") {
            alertify.notify(result.message, messageType);
        } else if (table) {
            table.alert(msg, type);
        } else {
            console.log(type, msg);
        }
    }

    /**
     * @returns {string}
     */
    function getSecurityID() {
        var el = document.querySelector("input[name=SecurityID]");
        return el ? el.value : null;
    }

    /**
     * @returns {string}
     */
    function getTabID() {
        let tabId = sessionStorage.getItem("tabulatorTabID");
        if (!tabId) {
            tabId = Math.round(Date.now()).toString(36);
            sessionStorage.setItem("tabulatorTabID", tabId);
        }
        return tabId;
    }

    /**
     * This helps restoring the proper tabs if needed after navigation or actions
     */
    function persistHash() {
        document.cookie = "hash=" + (window.location.hash || "") + "; path=/";
    }

    // Public api

    var buttonHandler = function (e, cell) {
        var btn = cell.getElement().querySelector("a,input,button");

        if (btn) {
            var styles = window.getComputedStyle(btn);
            // No trigger if hidden, disabled or readonly (attr or class)
            if (
                styles.display === "none" ||
                styles.visibility === "hidden" ||
                btn.disabled ||
                btn.readonly ||
                btn.classList.contains("disabled") ||
                btn.classList.contains("readonly")
            ) {
                e.preventDefault();
                return;
            }
            if (btn.dataset.ajax) {
                e.preventDefault();

                var formData = new FormData();
                formData.append("Action", btn.dataset.action || "");
                formData.append("SecurityID", getSecurityID());
                formData.append("Data", cell.getRow().getData());

                // We can have a custom ajax handler
                if (btn.dataset.ajax != 1 && btn.dataset.ajax != "true") {
                    var cb = getGlobalFn(btn.dataset.ajax);
                    if (!cb) {
                        console.warn("Handler not found", btn.dataset.ajax);
                    } else {
                        cb(e, cell, btn, formData);
                    }
                } else {
                    handleAction(
                        btn,
                        btn.getAttribute("href"),
                        formData,
                        (json) => {
                            defaultActionHandler(json, cell.getTable());
                        }
                    );
                }
            }
        }
    };

    var dataAjaxResponse = function (url, params, response) {
        if (!response.data) {
            console.error("Response does not contain a data key");
        }
        return response.data;
    };

    var boolGroupHeader = function (value, count, data, group) {
        if (value) {
            return group._group.field + " (" + count + ")";
        }
        return "(" + count + ")";
    };

    var isCellEditable = function (cell) {
        return cell._cell.row.data["_editable"];
    };

    var disableCallback = false;
    var cellEditedCallback = function (cell) {
        if (disableCallback) {
            return;
        }

        var value = cell.getValue();
        var column = cell.getColumn().getField();
        var data = cell.getRow().getData();
        var editUrl = cell.getTable().element.parentElement.dataset.editUrl;
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

        fetchWrapper(editUrl, {
            method: "POST",
            body: formData,
        })
            .then((json) => {
                notify(json.message, json.status || "success");

                if (json.value && json.value != value) {
                    cell.setValue(json.value);
                } else if (!cell.getValue() && value) {
                    cell.setValue(value);
                }
                disableCallback = false;
            })
            .catch((message) => {
                notify(message, "bad");
                disableCallback = false;
            });
    };

    /**
     * Forwards clicks on cell to checkbox
     * @param {Event} e
     * @param {Cell} cell
     */
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

    /**
     * @param {Cell} cell
     * @returns {HTMLElement}
     */
    var getGroupForCell = function (cell) {
        var el = cell.getElement();
        var row = el.closest(".tabulator-row");
        var previousSibling = row.previousSibling;
        while (
            previousSibling &&
            !previousSibling.classList.contains("tabulator-group")
        ) {
            previousSibling = previousSibling.previousSibling;
        }
        return previousSibling;
    };

    var getLoader = function () {
        return loader;
    };

    var rowMoved = function (row) {
        var data = row.getData();
        var moveUrl = row.getTable().element.parentElement.dataset.moveUrl;
        if (!moveUrl) {
            return;
        }

        // Since it's 0 based, we need to add 1
        var index = row.getTable().rowManager.getRowIndex(row) + 1;

        moveUrl = interpolate(moveUrl, data);

        var formData = new FormData();
        formData.append("SecurityID", getSecurityID());
        formData.append("Data", JSON.stringify(data));
        formData.append("Sort", index);

        // console.log(`moving # ${data.ID} from ${data.Sort} to ${index}`);

        fetchWrapper(moveUrl, {
            method: "POST",
            body: formData,
        })
            .then((json) => {
                notify(json.message, json.status || "success");
            })
            .catch((message) => {
                notify(message, "bad");
            });
    };

    function globalSearch(el, tabulator, customEl) {
        // Global search
        var globalSearch = document.getElementById(
            el.getAttribute("id") + "-search"
        );
        if (!globalSearch) {
            return;
        }
        var collectFilters = (wildcard, quick) => {
            var obj = [];
            if (wildcard) {
                obj.push({
                    field: "__wildcard",
                    type: "=",
                    value: wildcard.value,
                });
            }
            if (quick) {
                obj.push({
                    field: "__quickfilter",
                    type: "=",
                    value: quick.value,
                });
            }
            return obj;
        };
        var globalSearchInput = globalSearch.querySelector("input");
        var quickFilterSelect = globalSearch.querySelector("select");
        var debouncedSearchFunc = debounce(() => {
            tabulator.setFilter(
                collectFilters(globalSearchInput, quickFilterSelect)
            );
        });
        if (globalSearchInput) {
            globalSearchInput.addEventListener("input", debouncedSearchFunc);
        }

        if (quickFilterSelect) {
            quickFilterSelect.addEventListener("change", () => {
                tabulator.setFilter(
                    collectFilters(globalSearchInput, quickFilterSelect)
                );
            });
        }
    }

    function bulkSupport(el, tabulator, customEl) {
        // we should probably scope this better
        var confirm =
            tabulator.element.parentElement.parentElement.querySelector(
                ".tabulator-bulk-confirm"
            );

        if (!confirm) {
            return;
        }
        confirm.addEventListener("click", function (e) {
            e.preventDefault();

            var selectedData = tabulator.getSelectedData();
            var bulkEndpoint = customEl.dataset.bulkUrl;
            var select = this.parentElement.querySelector(
                ".tabulator-bulk-select"
            );
            var options = tabulator.options;
            var selectedAction = select.options[select.selectedIndex];
            if (!selectedAction.getAttribute("value")) {
                notify(options.langs[options.locale].bulkActions.no_action);
                return;
            }
            if (!selectedData.length) {
                notify(options.langs[options.locale].bulkActions.no_records);
                return;
            }

            var destructive = selectedAction.dataset.destructive;
            var xhr = selectedAction.dataset.xhr;

            if (destructive) {
                var res = window.confirm(
                    options.langs[options.locale].bulkActions.destructive
                );
                if (!res) {
                    return;
                }
            }

            var records = selectedData.map((item) => item.ID);
            var formData = new FormData();
            formData.append("Action", selectedAction.getAttribute("value"));
            formData.append("SecurityID", getSecurityID());
            formData.append("records[]", records);

            bulkEndpoint = bulkEndpoint.replace(/\/$/, "") + "/";
            var endpoint = bulkEndpoint + selectedAction.getAttribute("value");
            if (xhr) {
                handleAction(confirm, endpoint, formData, (json) => {
                    defaultActionHandler(json, tabulator);
                });
            } else {
                window.location = endpoint + "?records=" + records.join(",");
            }
        });
    }

    function addExisting(tools, tabulator) {
        let btn = tools.querySelector(".tabulator-add-existing");
        if (!btn) {
            return;
        }
        let ac = btn.parentElement.querySelector("bs-autocomplete");
        btn.addEventListener("click", (ev) => {
            let input = ac.querySelector('input[type="hidden"]');
            if (!input.value) {
                notify(btn.dataset.emptyMessage, "bad");
                return;
            }

            const endpoint = btn.dataset.endpoint;
            const formData = new FormData();
            formData.append("RecordID", input.value);
            fetchWrapper(endpoint, {
                method: "POST",
                body: formData,
            })
                .then((json) => {
                    notify(json.message, json.status || "success");
                    tabulator.setData();
                })
                .catch((message) => {
                    notify(message, "bad");
                });
        });
    }

    var configCallback = function (config) {
        // Helps to get per tab server side session
        // const tabId = getTabID();
        // config.ajaxParams = config.ajaxParams || {};
        // config.ajaxParams.TabID = tabId;
    };

    var initCallback = function (tabulator, customEl) {
        var el = tabulator.element;
        var holder = customEl.parentElement;
        var tools = holder.querySelector(".tabulator-tools");

        tabulator.on("cellEdited", cellEditedCallback);

        globalSearch(el, tabulator, customEl);
        bulkSupport(el, tabulator, customEl);
        addExisting(tools, tabulator);

        // Deal with state
        el.parentElement.addEventListener("click", function () {
            persistHash();
        });
    };

    // Public api
    var publicApi = {
        buttonHandler,
        boolGroupHeader,
        dataAjaxResponse,
        isCellEditable,
        getGroupByKey,
        getGroupForCell,
        getLoader,
        rowMoved,
        getGlobalFn,
        initCallback,
        configCallback,
        getTabID,
    };

    // You can extend this with your own features
    window.SSTabulator = window.SSTabulator || {};
    window.SSTabulator = Object.assign(window.SSTabulator, publicApi);
})();
