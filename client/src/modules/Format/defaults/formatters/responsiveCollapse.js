export default function(cell, formatterParams, onRendered){
	var el = document.createElement("div"),
	config = cell.getRow()._row.modules.responsiveLayout;

	el.classList.add("tabulator-responsive-collapse-toggle");
	el.innerHTML = "<span class='tabulator-responsive-collapse-toggle-open'>+</span><span class='tabulator-responsive-collapse-toggle-close'>-</span>";

	cell.getElement().classList.add("tabulator-row-handle");

	function toggleList(isOpen){
		var collapseEl = config.element;

		config.open = isOpen;

        if(config.open){
            el.classList.add("open");
        }else{
            el.classList.remove("open");
        }

        if(collapseEl){
            collapseEl.style.display = isOpen ? "" : "none";
        }
	}

	el.addEventListener("click", function(e){
		e.stopImmediatePropagation();
        cell.getTable().modules.resizeTable.blockRedraw = true;
		toggleList(!config.open);
        cell.getRow()._row.dispatch("row-responsive-toggled", cell.getRow(), config.open);
        cell.getTable().rowManager.adjustTableSize();
        setTimeout(() => {
            cell.getTable().modules.resizeTable.blockRedraw = false;
        }, 100);
	});

	toggleList(config.open);

	return el;
};
