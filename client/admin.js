(function ($) {
    // Hopefully it gets merged into core
    // @link https://github.com/silverstripe/silverstripe-admin/issues/1308
    $.entwine("ss", function ($) {
        $(".ss-tabset, .cms-tabset").entwine({
            triggerLazyLoad: function (panel, selector = ".lazy-loadable") {
                panel.find(selector).each((idx, el) => {
                    var $el = $(el);
                    var lazyEvent = el.dataset.lazyEvent || "lazyload";
                    if ($el.closest(".ss-tabset, .cms-tabset").is(this)) {
                        // This should be listened only once
                        el.dispatchEvent(new Event(lazyEvent));
                    }
                });
            },

            onadd: function () {
                this.on(
                    "tabsactivate",
                    function (event, { newPanel }) {
                        this.triggerLazyLoad(newPanel);
                    }.bind(this)
                );
                this.on(
                    "tabscreate",
                    function (event, { panel }) {
                        this.triggerLazyLoad(panel);
                    }.bind(this)
                );
                this._super();
            },
        });
    });
})(jQuery);
