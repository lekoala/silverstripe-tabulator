import Format from "../../../node_modules/tabulator-tables/src/js/modules/Format/Format.js";

import defaultFormatters from "../../../node_modules/tabulator-tables/src/js/modules/Format/defaults/formatters.js";
import { default as myResponsiveCollapse } from "./defaults/formatters/responsiveCollapse.js";

class MyFormat extends Format {}

MyFormat.moduleName = "format";

//load defaults
MyFormat.formatters = defaultFormatters;

//replace responsive collapse
MyFormat.formatters['responsiveCollapse'] = myResponsiveCollapse;

export default MyFormat;
