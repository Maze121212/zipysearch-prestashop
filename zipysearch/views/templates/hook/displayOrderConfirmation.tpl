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
(function() {
  if (typeof ZipySearch !== 'undefined' && ZipySearch.trackConversion) {
    ZipySearch.trackConversion();
  } else {
    var img = new Image();
    img.src = '{$zipysearch_api_url|escape:'javascript':'UTF-8'}/api.php?action=log-conversion' +
              '&tenant={$zipysearch_tenant|escape:'url':'UTF-8'}' +
              '&url=' + encodeURIComponent(window.location.href) +
              '&pixel=1';
  }
})();
</script>
