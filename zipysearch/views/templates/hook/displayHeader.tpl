{* ZipySearch Widget *}
<script src="{$zipysearch_api_url|escape:'htmlall':'UTF-8'}/widget/zipysearch.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof ZipySearch !== 'undefined') {
    ZipySearch.init({
      inputSelector: '{$zipysearch_input_selector nofilter}',
      apiUrl: '{$zipysearch_api_url|escape:'javascript'}',
      tenant: '{$zipysearch_tenant|escape:'javascript'}'{if $zipysearch_debug},
      debug: true{/if}
    });
  }
});
</script>
