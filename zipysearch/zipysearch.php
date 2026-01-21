<?php
/**
 * ZipySearch - Moteur de recherche intelligent
 *
 * @author    ZipySearch <contact@zipysearch.com>
 * @copyright ZipySearch
 * @license   Commercial license
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ZipySearch extends Module
{
    const API_URL = 'https://api-search.zipybot.com';
    const ADMIN_URL = 'https://search.zipybot.com';

    public function __construct()
    {
        $this->name = 'zipysearch';
        $this->tab = 'search_filter';
        $this->version = '1.0.0';
        $this->author = 'ZipySearch';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('ZipySearch - Moteur de recherche intelligent');
        $this->description = $this->l('Remplace la recherche PrestaShop par ZipySearch avec autocompletion, filtres et analytics.');
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        // Generer token unique
        Configuration::updateValue('ZIPYSEARCH_EXPORT_TOKEN', bin2hex(random_bytes(32)));
        Configuration::updateValue('ZIPYSEARCH_WIDGET_ENABLED', 1);
        Configuration::updateValue('ZIPYSEARCH_INPUT_SELECTOR', 'input[name="s"]');
        Configuration::updateValue('ZIPYSEARCH_CONVERSION_TRACKING', 1);

        return parent::install()
            && $this->installSql()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayOrderConfirmation');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallSql()
            && Configuration::deleteByName('ZIPYSEARCH_TENANT_SLUG')
            && Configuration::deleteByName('ZIPYSEARCH_API_KEY')
            && Configuration::deleteByName('ZIPYSEARCH_EXPORT_TOKEN')
            && Configuration::deleteByName('ZIPYSEARCH_WIDGET_ENABLED')
            && Configuration::deleteByName('ZIPYSEARCH_INPUT_SELECTOR')
            && Configuration::deleteByName('ZIPYSEARCH_CONVERSION_TRACKING')
            && Configuration::deleteByName('ZIPYSEARCH_DEBUG_MODE');
    }

    private function installSql()
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        return Db::getInstance()->execute($sql);
    }

    private function uninstallSql()
    {
        $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        return Db::getInstance()->execute($sql);
    }

    // Hook header - injecter le widget
    public function hookDisplayHeader($params)
    {
        if (!Configuration::get('ZIPYSEARCH_WIDGET_ENABLED')) {
            return '';
        }
        $tenant = Configuration::get('ZIPYSEARCH_TENANT_SLUG');
        if (!$tenant) {
            return '';
        }

        // Decode HTML entities in selector (PrestaShop may encode quotes)
        $inputSelector = Configuration::get('ZIPYSEARCH_INPUT_SELECTOR') ?: 'input[name="s"]';
        $inputSelector = html_entity_decode($inputSelector, ENT_QUOTES, 'UTF-8');

        $this->context->smarty->assign([
            'zipysearch_api_url' => self::API_URL,
            'zipysearch_tenant' => $tenant,
            'zipysearch_input_selector' => $inputSelector,
            'zipysearch_debug' => (bool)Configuration::get('ZIPYSEARCH_DEBUG_MODE'),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/displayHeader.tpl');
    }

    // Hook conversion
    public function hookDisplayOrderConfirmation($params)
    {
        if (!Configuration::get('ZIPYSEARCH_CONVERSION_TRACKING')) {
            return '';
        }
        $tenant = Configuration::get('ZIPYSEARCH_TENANT_SLUG');
        if (!$tenant) {
            return '';
        }

        $this->context->smarty->assign([
            'zipysearch_api_url' => self::API_URL,
            'zipysearch_tenant' => $tenant,
        ]);
        return $this->display(__FILE__, 'views/templates/hook/displayOrderConfirmation.tpl');
    }

    // Page de configuration
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit_zipysearch')) {
            $tenantSlug = Tools::getValue('tenant_slug');
            $apiKey = Tools::getValue('api_key');

            Configuration::updateValue('ZIPYSEARCH_TENANT_SLUG', $tenantSlug);
            Configuration::updateValue('ZIPYSEARCH_API_KEY', $apiKey);
            Configuration::updateValue('ZIPYSEARCH_WIDGET_ENABLED', (int)Tools::getValue('widget_enabled'));
            Configuration::updateValue('ZIPYSEARCH_INPUT_SELECTOR', Tools::getValue('input_selector'));
            Configuration::updateValue('ZIPYSEARCH_CONVERSION_TRACKING', (int)Tools::getValue('conversion_tracking'));
            Configuration::updateValue('ZIPYSEARCH_DEBUG_MODE', (int)Tools::getValue('debug_mode'));

            // Synchroniser avec ZipySearch si les identifiants sont renseignés
            if ($tenantSlug && $apiKey) {
                $syncResult = $this->syncWithZipySearch($tenantSlug, $apiKey);
                if ($syncResult['success']) {
                    $output .= $this->displayConfirmation($this->l('Configuration sauvegardee et synchronisee avec ZipySearch'));
                } else {
                    $output .= $this->displayConfirmation($this->l('Configuration sauvegardee'));
                    $output .= $this->displayWarning($this->l('Synchronisation avec ZipySearch echouee: ') . $syncResult['error']);
                }
            } else {
                $output .= $this->displayConfirmation($this->l('Configuration sauvegardee'));
            }
        }

        if (Tools::isSubmit('regenerate_token')) {
            Configuration::updateValue('ZIPYSEARCH_EXPORT_TOKEN', bin2hex(random_bytes(32)));
            $output .= $this->displayConfirmation($this->l('Token regenere'));

            // Resynchroniser avec ZipySearch si les identifiants sont renseignés
            $tenantSlug = Configuration::get('ZIPYSEARCH_TENANT_SLUG');
            $apiKey = Configuration::get('ZIPYSEARCH_API_KEY');
            if ($tenantSlug && $apiKey) {
                $this->syncWithZipySearch($tenantSlug, $apiKey);
            }
        }

        return $output . $this->renderInstructions() . $this->renderForm();
    }

    /**
     * Synchronise la configuration avec ZipySearch
     */
    private function syncWithZipySearch($tenantSlug, $apiKey)
    {
        $exportUrl = $this->context->link->getModuleLink('zipysearch', 'export', ['token' => Configuration::get('ZIPYSEARCH_EXPORT_TOKEN')]);
        $shopDomain = $this->context->shop->domain;
        $inputSelector = Configuration::get('ZIPYSEARCH_INPUT_SELECTOR') ?: 'input[name="s"]';

        $data = [
            'slug' => $tenantSlug,
            'apiKey' => $apiKey,
            'csvUrl' => $exportUrl,
            'csvDelimiter' => ';',
            'allowedOrigins' => [$shopDomain],
            'inputSelector' => $inputSelector,
        ];

        $ch = curl_init(self::ADMIN_URL . '/api/module/configure');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // Suivre les redirections (307, 308, etc.)
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        // Maintenir la méthode POST lors des redirections 307/308
        curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode !== 200) {
            $responseData = json_decode($response, true);
            return ['success' => false, 'error' => $responseData['error'] ?? 'Erreur HTTP ' . $httpCode];
        }

        return ['success' => true];
    }

    private function renderExportUrlField()
    {
        $exportUrl = $this->context->link->getModuleLink('zipysearch', 'export', ['token' => Configuration::get('ZIPYSEARCH_EXPORT_TOKEN')]);
        $copyText = $this->l('Copier');
        $copiedText = $this->l('Copie !');

        return '
        <div class="input-group" style="max-width: 600px;">
            <input type="text" id="zipysearch_export_url" class="form-control" value="' . htmlspecialchars($exportUrl) . '" readonly onclick="this.select();" style="font-family: monospace; font-size: 12px;">
            <span class="input-group-btn">
                <button type="button" class="btn btn-default" onclick="copyExportUrl()" title="' . $copyText . '">
                    <i class="icon-copy"></i> ' . $copyText . '
                </button>
            </span>
        </div>
        <p class="help-block" style="margin-top: 8px;">
            ' . $this->l('Communiquez cette URL a ZipySearch pour configurer l\'import automatique de vos produits.') . '
        </p>
        <script>
        function copyExportUrl() {
            var input = document.getElementById("zipysearch_export_url");
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand("copy");
            var btn = event.target.closest("button");
            var originalHtml = btn.innerHTML;
            btn.innerHTML = "<i class=\"icon-check\"></i> ' . $copiedText . '";
            btn.classList.add("btn-success");
            setTimeout(function() {
                btn.innerHTML = originalHtml;
                btn.classList.remove("btn-success");
            }, 2000);
        }
        </script>';
    }

    private function renderInstructions()
    {
        $html = '<div class="panel">
            <h3><i class="icon-info-circle"></i> ' . $this->l('Comment obtenir votre ID de compte ?') . '</h3>
            <ol style="margin: 15px 0; padding-left: 20px; line-height: 1.8;">
                <li>' . $this->l('Rendez-vous sur') . ' <a href="https://search.zipybot.com" target="_blank" style="color: #25b9d7; font-weight: bold;">search.zipybot.com</a></li>
                <li>' . $this->l('Creez votre compte gratuitement (1000 requetes/mois offertes)') . '</li>
                <li>' . $this->l('Recuperez votre ID de compte sur le Dashboard ou dans votre Profil') . '</li>
                <li>' . $this->l('Collez-le dans le champ ci-dessous') . '</li>
            </ol>
            <p style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                <i class="icon-lightbulb-o"></i> ' . $this->l('Une fois configure, le widget de recherche remplacera automatiquement la recherche native de votre boutique.') . '
            </p>
        </div>';
        return $html;
    }

    private function renderForm()
    {
        $adminUrl = self::ADMIN_URL;

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuration ZipySearch'),
                    'icon' => 'icon-search',
                ],
                'input' => [
                    [
                        'type' => 'html',
                        'label' => '',
                        'name' => 'help_info',
                        'html_content' => '
                            <div class="alert alert-info">
                                <h4><i class="icon-info-circle"></i> ' . $this->l('Comment configurer ZipySearch ?') . '</h4>
                                <ol>
                                    <li>' . $this->l('Rendez-vous sur') . ' <a href="' . $adminUrl . '" target="_blank"><strong>' . $adminUrl . '</strong></a></li>
                                    <li>' . $this->l('Créez votre compte gratuitement') . ' <strong>(' . $this->l('1000 requêtes/mois offertes') . ')</strong></li>
                                    <li>' . $this->l('Récupérez votre') . ' <strong>' . $this->l('ID de compte') . '</strong> ' . $this->l('et') . ' <strong>' . $this->l('Clé API') . '</strong> ' . $this->l('dans la page') . ' <a href="' . $adminUrl . '/profile" target="_blank">' . $this->l('Mon compte') . '</a> ' . $this->l('(section Entreprise)') . '</li>
                                    <li>' . $this->l('Collez ces valeurs dans les champs ci-dessous et') . ' <strong>' . $this->l('sauvegardez') . '</strong></li>
                                    <li>' . $this->l('Rendez-vous sur') . ' <a href="' . $adminUrl . '/imports" target="_blank"><strong>' . $this->l('Gestion des Imports') . '</strong></a> ' . $this->l('pour importer vos produits') . '</li>
                                </ol>
                                <p style="margin-top: 10px; margin-bottom: 0;">
                                    <i class="icon-lightbulb-o"></i> ' . $this->l('Une fois configuré, le widget de recherche remplacera automatiquement la recherche native de votre boutique.') . '
                                </p>
                            </div>
                        ',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('ID de compte ZipySearch'),
                        'name' => 'tenant_slug',
                        'desc' => $this->l('Identifiant unique de votre compte (ex: ma-boutique)'),
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Clé API'),
                        'name' => 'api_key',
                        'desc' => $this->l('Permet la configuration automatique de l\'URL d\'export produits'),
                        'required' => true,
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('URL d\'export produits'),
                        'name' => 'export_url_html',
                        'html_content' => $this->renderExportUrlField(),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Activer le widget'),
                        'name' => 'widget_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Selecteur CSS du champ recherche'),
                        'name' => 'input_selector',
                        'desc' => $this->l('Defaut: input[name="s"]'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Tracker les conversions'),
                        'name' => 'conversion_tracking',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'conv_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'conv_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Mode debug'),
                        'name' => 'debug_mode',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'debug_on', 'value' => 1, 'label' => $this->l('Oui')],
                            ['id' => 'debug_off', 'value' => 0, 'label' => $this->l('Non')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Sauvegarder'),
                ],
                'buttons' => [
                    [
                        'type' => 'submit',
                        'name' => 'regenerate_token',
                        'title' => $this->l('Regenerer le token'),
                        'icon' => 'process-icon-refresh',
                        'class' => 'pull-right',
                    ],
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submit_zipysearch';
        $helper->fields_value = [
            'tenant_slug' => Configuration::get('ZIPYSEARCH_TENANT_SLUG'),
            'api_key' => Configuration::get('ZIPYSEARCH_API_KEY'),
            'widget_enabled' => Configuration::get('ZIPYSEARCH_WIDGET_ENABLED'),
            'input_selector' => Configuration::get('ZIPYSEARCH_INPUT_SELECTOR'),
            'conversion_tracking' => Configuration::get('ZIPYSEARCH_CONVERSION_TRACKING'),
            'debug_mode' => Configuration::get('ZIPYSEARCH_DEBUG_MODE'),
        ];

        return $helper->generateForm([$fields_form]);
    }
}
