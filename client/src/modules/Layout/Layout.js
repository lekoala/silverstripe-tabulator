import Module from '../../core/Module.js';

import defaultModes from './defaults/modes.js';

class Layout extends Module{

	constructor(table){
		super(table, "layout");

		this.mode = null;

		this.registerTableOption("layout", "fitData"); //layout type
		this.registerTableOption("layoutColumnsOnNewData", false); //update column widths on setData

		this.registerColumnOption("widthGrow");
		this.registerColumnOption("widthShrink");
	}

	//initialize layout system
	initialize(){
		var layout = this.table.options.layout;

		if(Layout.modes[layout]){
			this.mode = layout;
		}else{
			console.warn("Layout Error - invalid mode set, defaulting to 'fitData' : " + layout);
			this.mode = 'fitData';
		}

		this.table.element.setAttribute("tabulator-layout", this.mode);
	}

	getMode(){
		return this.mode;
	}

	//trigger table layout
	layout(){
		this.dispatch("layout-refreshing")
		Layout.modes[this.mode].call(this, this.table.columnManager.columnsByIndex);

		this.dispatch("layout-refreshed")
	}
}

Layout.moduleName = "layout";

//load defaults
Layout.modes = defaultModes;

export default Layout;