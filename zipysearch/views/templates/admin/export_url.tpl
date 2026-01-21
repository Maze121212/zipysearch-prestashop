{*
 * ZipySearch - Intelligent Search Engine
 *
 * @author    ZipySearch <contact@zipysearch.com>
 * @copyright ZipySearch
 * @license   Commercial license
 *}

<div class="input-group" style="max-width: 600px;">
    <input type="text" id="zipysearch_export_url" class="form-control" value="{$zipysearch_export_url|escape:'htmlall':'UTF-8'}" readonly onclick="this.select();" style="font-family: monospace; font-size: 12px;">
    <span class="input-group-btn">
        <button type="button" id="zipysearch_copy_btn" class="btn btn-default" onclick="copyZipySearchExportUrl()" title="{l s='Copy' mod='zipysearch'}">
            <i class="icon-copy"></i> <span id="zipysearch_copy_text">{l s='Copy' mod='zipysearch'}</span>
        </button>
    </span>
</div>
<script>
function copyZipySearchExportUrl() {
    var input = document.getElementById("zipysearch_export_url");
    input.select();
    input.setSelectionRange(0, 99999);
    document.execCommand("copy");
    var btn = document.getElementById("zipysearch_copy_btn");
    var textSpan = document.getElementById("zipysearch_copy_text");
    var icon = btn.querySelector("i");
    var originalText = textSpan.textContent;
    var originalIconClass = icon.className;
    textSpan.textContent = "{l s='Copied!' mod='zipysearch' js=1}";
    icon.className = "icon-check";
    btn.classList.add("btn-success");
    setTimeout(function() {
        textSpan.textContent = originalText;
        icon.className = originalIconClass;
        btn.classList.remove("btn-success");
    }, 2000);
}
</script>
