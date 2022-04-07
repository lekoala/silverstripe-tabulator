<div id="$ID"></div>
<script type="module">
var table = new Tabulator("#$ID", $JsonOptions);
table.on("rowClick", function(e, row){
    console.log(row._row.data);
});

// Mitigate issue https://github.com/olifolkerd/tabulator/issues/3692
document.querySelector("#$ID").addEventListener('keydown', function(e) {
    if(e.keyCode == 13) {
        e.preventDefault();
    }
});
</script>
