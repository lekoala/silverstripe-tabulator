import ResizeTable from "../../../node_modules/tabulator-tables/src/js/modules/ResizeTable/ResizeTable.js";

/**
 * Adds a couple of things to avoid unecessary redraws
 */
class MyResizeTable extends ResizeTable {
    blockRedraw() {
        this.redrawBlock = true;
    }

    restoreRedraw() {
        this.redrawBlock = false;
    }

    redrawTable(force) {
        if (this.redrawBlock) return;

        if (this.initialized && this.visible) {
            this.table.columnManager.rerenderColumns(true);
            this.table.redraw(force);
        }
    }

    tableResized() {
        if (this.redrawBlock) return;

        this.table.rowManager.redraw();
    }
}

MyResizeTable.moduleName = "resizeTable";

export default MyResizeTable;
