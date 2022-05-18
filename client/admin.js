(function ($) {
    $.entwine("ss", function ($) {
        function triggerLazyLoad(el) {
            el.find(".lazy-loadable").each(function (idx, ele) {
                ele.dispatchEvent(new Event("lazyloaded"), { once: true });
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
