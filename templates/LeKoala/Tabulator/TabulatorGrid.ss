<div class="tabulator-tools">
<div class="tabulator-tools-start"><% loop $ShowTools(start) %>$Me<% end_loop %></div>
<div class="tabulator-tools-end"><% loop $ShowTools(end) %>$Me<% end_loop %></div>
</div>
<div id="$ID" class="$extraClass"></div>
<script type="module">
    SSTabulator.init("#$ID", $JsonOptions);
</script>
