//tabulator with all modules installed
import { default as Tabulator } from "../../node_modules/tabulator-tables/src/js/core/Tabulator.js";
import * as modules from "./optional.js";
import ModuleBinder from "../../node_modules/tabulator-tables/src/js/core/tools/ModuleBinder.js";

class TabulatorFull extends Tabulator {}

//bind modules and static functionality
new ModuleBinder(TabulatorFull, modules);

export default TabulatorFull;
