{* ZipySearch Conversion Tracking *}
<script>
(function() {
  if (typeof ZipySearch !== 'undefined' && ZipySearch.trackConversion) {
    ZipySearch.trackConversion();
  } else {
    var img = new Image();
    img.src = '{$zipysearch_api_url|escape:'javascript'}/api.php?action=log-conversion' +
              '&tenant={$zipysearch_tenant|escape:'url'}' +
              '&url=' + encodeURIComponent(window.location.href) +
              '&pixel=1';
  }
})();
</script>
