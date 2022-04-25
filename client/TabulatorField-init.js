(function () {
    document.querySelectorAll('.tabulatorgrid').forEach((el) => {
        SSTabulator.init(el.getAttribute('id'));
    });
})();
