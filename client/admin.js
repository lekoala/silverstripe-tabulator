(function ($) {
    // Hopefully it gets merged into core
    // @link https://github.com/silverstripe/silverstripe-admin/issues/1308
    $.entwine("ss", function ($) {
        function triggerLazyLoad(
            panel,
            currentTabset,
            selector = ".lazy-loadable"
        ) {
            panel.find(selector).each(function (idx, el) {
                var $el = $(el);
                var lazyEvent = el.dataset.lazyEvent || "lazyload";
                if ($el.closest(".ss-tabset, .cms-tabset").is(currentTabset)) {
                    el.dispatchEvent(new Event(lazyEvent));
                }
            });
        }

        $(".ss-tabset, .cms-tabset").entwine({
            onadd: function () {
                this.on(
                    "tabsactivate",
                    function (event, { newPanel }) {
                        triggerLazyLoad(newPanel, this);
                    }.bind(this)
                );
                this.on(
                    "tabscreate",
                    function (event, { panel }) {
                        triggerLazyLoad(panel, this);
                    }.bind(this)
                );
                this._super();
            },
        });
    });
})(jQuery);
