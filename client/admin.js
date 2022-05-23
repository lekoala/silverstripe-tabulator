(function ($) {
    // Hopefully it gets merged into core
    // @link https://github.com/silverstripe/silverstripe-admin/issues/1308
    $.entwine("ss", function ($) {
        function triggerLazyLoad(el, selector = ".lazy-loadable") {
            el.find(selector).each(function (idx, ele) {
                var lazyEvent = ele.dataset.lazyEvent || "lazyload";
                ele.dispatchEvent(new Event(lazyEvent), { once: true });
            });
        }

        $(".ss-tabset, .cms-tabset").entwine({
            onadd: function () {
                this.on(
                    "tabsactivate",
                    function (event, { newPanel }) {
                        triggerLazyLoad(newPanel);
                    }.bind(this)
                );
                this.on(
                    "tabscreate",
                    function (event, { panel }) {
                        triggerLazyLoad(panel);
                    }.bind(this)
                );
                this._super();
            },
        });
    });
})(jQuery);
