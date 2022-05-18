import Module from '../../core/Module.js';

class ReactiveData extends Module{

	constructor(table){
		super(table);

		this.data = false;
		this.blocked = false; //block reactivity while performing update
		this.origFuncs = {}; // hold original data array functions to allow replacement after data is done with
		this.currentVersion = 0;

		this.registerTableOption("reactiveData", false); //enable data reactivity
	}

	initialize(){
		if(this.table.options.reactiveData){
			this.subscribe("cell-value-save-before", this.block.bind(this));
			this.subscribe("cell-value-save-after", this.unblock.bind(this));
			this.subscribe("row-data-save-before", this.block.bind(this));
			this.subscribe("row-data-save-after", this.unblock.bind(this));
			this.subscribe("row-data-init-after", this.watchRow.bind(this));
			this.subscribe("data-processing", this.watchData.bind(this));
			this.subscribe("table-destroy", this.unwatchData.bind(this));
		}
	}

	watchData(data){
		var self = this,
		pushFunc, version;

		this.currentVersion ++;

		version = this.currentVersion;

		this.unwatchData();

		this.data = data;

		//override array push function
		this.origFuncs.push = data.push;

		Object.defineProperty(this.data, "push", {
			enumerable: false,
			configurable: true,
			value: function(){
				var args = Array.from(arguments);

				if(!self.blocked && version === self.currentVersion){
					args.forEach((arg) => {
						self.table.rowManager.addRowActual(arg, false);
					});
				}

				return self.origFuncs.push.apply(data, arguments);
			}
		});

		//override array unshift function
		this.origFuncs.unshift = data.unshift;

		Object.defineProperty(this.data, "unshift", {
			enumerable: false,
			configurable: true,
			value: function(){
				var args = Array.from(arguments);

				if(!self.blocked && version === self.currentVersion){
					args.forEach((arg) => {
						self.table.rowManager.addRowActual(arg, true);
					});
				}

				return self.origFuncs.unshift.apply(data, arguments);
			}
		});


		//override array shift function
		this.origFuncs.shift = data.shift;

		Object.defineProperty(this.data, "shift", {
			enumerable: false,
			configurable: true,
			value: function(){
				var row;

				if(!self.blocked && version === self.currentVersion){
					if(self.data.length){
						row = self.table.rowManager.getRowFromDataObject(self.data[0]);

						if(row){
							row.deleteActual();
						}
					}
				}

				return self.origFuncs.shift.call(data);
			}
		});

		//override array pop function
		this.origFuncs.pop = data.pop;

		Object.defineProperty(this.data, "pop", {
			enumerable: false,
			configurable: true,
			value: function(){
				var row;
				if(!self.blocked && version === self.currentVersion){
					if(self.data.length){
						row = self.table.rowManager.getRowFromDataObject(self.data[self.data.length - 1]);

						if(row){
							row.deleteActual();
						}
					}
				}
				return self.origFuncs.pop.call(data);
			}
		});


		//override array splice function
		this.origFuncs.splice = data.splice;

		Object.defineProperty(this.data, "splice", {
			enumerable: false,
			configurable: true,
			value: function(){
				var args = Array.from(arguments),
				start = args[0] < 0 ? data.length + args[0] : args[0],
				end = args[1],
				newRows = args[2] ? args.slice(2) : false,
				startRow;

				if(!self.blocked && version === self.currentVersion){

					//add new rows
					if(newRows){
						startRow = data[start] ? self.table.rowManager.getRowFromDataObject(data[start]) : false;

						if(startRow){
							newRows.forEach((rowData) => {
								self.table.rowManager.addRowActual(rowData, true, startRow, true);
							});
						}else{
							newRows = newRows.slice().reverse();

							newRows.forEach((rowData) => {
								self.table.rowManager.addRowActual(rowData, true, false, true);
							});
						}
					}

					//delete removed rows
					if(end !== 0){
						var oldRows = data.slice(start, typeof args[1] === "undefined" ? args[1] : start + end);

						oldRows.forEach((rowData, i) => {
							var row = self.table.rowManager.getRowFromDataObject(rowData);

							if(row){
								row.deleteActual(i !== oldRows.length - 1);
							}
						});
					}

					if(newRows || end !== 0){
						self.table.rowManager.reRenderInPosition();
					}
				}

				return self.origFuncs.splice.apply(data, arguments);
			}
		});
	}

	unwatchData(){
		if(this.data !== false){
			for(var key in this.origFuncs){
				Object.defineProperty(this.data, key, {
					enumerable: true,
					configurable:true,
					writable:true,
					value: this.origFuncs.key,
				});
			}
		}
	}

	watchRow(row){
		var data = row.getData();

		this.blocked = true;

		for(var key in data){
			this.watchKey(row, data, key);
		}

		if(this.table.options.dataTree){
			this.watchTreeChildren(row);
		}

		this.blocked = false;
	}

	watchTreeChildren (row){
		var childField = row.getData()[this.table.options.dataTreeChildField],
		origFuncs = {};

		function rebuildTree(){
			this.table.modules.dataTree.initializeRow(row);
			this.table.modules.dataTree.layoutRow(row);
			this.table.rowManager.refreshActiveData("tree", false, true);
		}

		if(childField){

			origFuncs.push = childField.push;

			Object.defineProperty(childField, "push", {
				enumerable: false,
				configurable: true,
				value: () => {
					var result = origFuncs.push.apply(childField, arguments);

					rebuildTree();

					return result;
				}
			});

			origFuncs.unshift = childField.unshift;

			Object.defineProperty(childField, "unshift", {
				enumerable: false,
				configurable: true,
				value: () => {
					var result =  origFuncs.unshift.apply(childField, arguments);

					rebuildTree();

					return result;
				}
			});

			origFuncs.shift = childField.shift;

			Object.defineProperty(childField, "shift", {
				enumerable: false,
				configurable: true,
				value: () => {
					var result =  origFuncs.shift.call(childField);

					rebuildTree();

					return result;
				}
			});

			origFuncs.pop = childField.pop;

			Object.defineProperty(childField, "pop", {
				enumerable: false,
				configurable: true,
				value: () => {
					var result =  origFuncs.pop.call(childField);

					rebuildTree();

					return result;
				}
			});

			origFuncs.splice = childField.splice;

			Object.defineProperty(childField, "splice", {
				enumerable: false,
				configurable: true,
				value: () => {
					var result =  origFuncs.splice.apply(childField, arguments);

					rebuildTree();

					return result;
				}
			});
		}
	}

	watchKey(row, data, key){
		var props = Object.getOwnPropertyDescriptor(data, key),
		value = data[key],
		version = this.currentVersion;

		Object.defineProperty(data, key, {
			set: (newValue) => {
				value = newValue;
				if(!this.blocked && version === this.currentVersion){
					var update = {};
					update[key] = newValue;
					row.updateData(update);
				}

				if(props.set){
					props.set(newValue);
				}
			},
			get:() => {

				if(props.get){
					props.get();
				}

				return value;
			}
		});
	}

	unwatchRow(row){
		var data = row.getData();

		for(var key in data){
			Object.defineProperty(data, key, {
				value:data[key],
			});
		}
	}

	block(){
		this.blocked = true;
	}

	unblock(){
		this.blocked = false;
	}
}

ReactiveData.moduleName = "reactiveData";

export default ReactiveData;



