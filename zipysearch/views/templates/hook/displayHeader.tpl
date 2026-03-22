{*
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    ZipySearch <contact@zipybot.com>
 * @copyright 2025 ZipySearch
 * @license   Academic Free License 3.0 (AFL-3.0)
 *}
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof ZipySearch !== 'undefined') {
    ZipySearch.init({
      inputSelector: '{$zipysearch_input_selector|escape:'javascript':'UTF-8'}',
      apiUrl: '{$zipysearch_api_url|escape:'javascript':'UTF-8'}',
      tenant: '{$zipysearch_tenant|escape:'javascript':'UTF-8'}'{if $zipysearch_debug},
      debug: true{/if}
    });
  }
});
</script>
