export default function(cell, formatterParams, onRendered){
	var open = false,
	el = document.createElement("div"),
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

        cell.getRow()._row.dispatch("row-responsive-toggled", cell.getRow(), isOpen);
        cell.getTable().rowManager.adjustTableSize();
	}

	el.addEventListener("click", function(e){
		e.stopImmediatePropagation();
		toggleList(!config.open);
	});

	toggleList(config.open);

	return el;
};
