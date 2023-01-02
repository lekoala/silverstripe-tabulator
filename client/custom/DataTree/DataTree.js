import DataTree from "../../../node_modules/tabulator-tables/src/js/modules/DataTree/DataTree.js";

/**
 * Adds a couple of things to avoid unecessary redraws
 */
class MyDataTree extends DataTree {
    expandRow(row, silent) {
        var config = row.modules.dataTree;

        if (config.children !== false) {
            config.open = true;

            // prevent redraw due to size change
            row.table.element.style.minHeight =
                row.table.element.offsetHeight + "px";

            row.reinitialize();

            this.refreshData(true);

            this.dispatchExternal(
                "dataTreeRowExpanded",
                row.getComponent(),
                row.modules.dataTree.index
            );
        }
    }

    collapseRow(row) {
        var config = row.modules.dataTree;

        if (config.children !== false) {
            config.open = false;

            // prevent redraw due to size change
            row.table.element.style.minHeight =
                row.table.element.offsetHeight + "px";

            row.reinitialize();

            this.refreshData(true);

            this.dispatchExternal(
                "dataTreeRowCollapsed",
                row.getComponent(),
                row.modules.dataTree.index
            );

            // adjust to collapsed size
            row.table.element.style.minHeight =
                row.table.element.querySelector(".tabulator-tableholder")
                    .offsetHeight + "px";
        }
    }
}

DataTree.moduleName = "dataTree";

export default MyDataTree;
