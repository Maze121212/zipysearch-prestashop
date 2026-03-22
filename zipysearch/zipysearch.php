<?php
/**
 * ZipySearch - Intelligent Search Engine
 *
 * @author    ZipySearch <contact@zipybot.com>
 * @copyright 2025 ZipySearch
 * @license   Academic Free License 3.0 (AFL-3.0)
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
        $this->version = '1.0.4';
        $this->author = 'ZipySearch';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = 'def6e7f534c263a0bd9e17a373be6e32';
        parent::__construct();
        $this->displayName = $this->l('ZipySearch - Intelligent Search Engine');
        $this->description = $this->l('Replace PrestaShop search with ZipySearch featuring autocomplete, filters and analytics.');
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        Configuration::updateValue('ZIPYSEARCH_EXPORT_TOKEN', bin2hex(random_bytes(32)));
        Configuration::updateValue('ZIPYSEARCH_WIDGET_ENABLED', 1);
        Configuration::updateValue('ZIPYSEARCH_INPUT_SELECTOR', 'input[name="s"]');
        Configuration::updateValue('ZIPYSEARCH_CONVERSION_TRACKING', 1);

        return parent::install()
            && $this->installSql()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBeforeBodyClosingTag')
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

    private function getInitHtml(): string
    {
        if (!Configuration::get('ZIPYSEARCH_WIDGET_ENABLED')) {
            return '';
        }
        $tenant = Configuration::get('ZIPYSEARCH_TENANT_SLUG');
        if (!$tenant) {
            return '';
        }

        $inputSelector = Configuration::get('ZIPYSEARCH_INPUT_SELECTOR') ?: 'input[name="s"]';
        $inputSelector = html_entity_decode($inputSelector, ENT_QUOTES, 'UTF-8');

        $this->context->smarty->assign([
            'zipysearch_api_url' => self::API_URL,
            'zipysearch_tenant' => $tenant,
            'zipysearch_input_selector' => $inputSelector,
            'zipysearch_debug' => (bool) Configuration::get('ZIPYSEARCH_DEBUG_MODE'),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/displayHeader.tpl');
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        // Charge le widget JS sur tous les thèmes y compris mobile
        if (!Configuration::get('ZIPYSEARCH_WIDGET_ENABLED')) {
            return;
        }
        $tenant = Configuration::get('ZIPYSEARCH_TENANT_SLUG');
        if (!$tenant) {
            return;
        }

        $inputSelector = Configuration::get('ZIPYSEARCH_INPUT_SELECTOR') ?: 'input[name="s"]';
        $inputSelector = html_entity_decode($inputSelector, ENT_QUOTES, 'UTF-8');

        // Passe la config au JS via window.zipysearchConfig (auto-init garanti même sans displayHeader)
        Media::addJsDef([
            'zipysearchConfig' => [
                'apiUrl' => self::API_URL,
                'tenant' => $tenant,
                'inputSelector' => $inputSelector,
                'debug' => (bool) Configuration::get('ZIPYSEARCH_DEBUG_MODE'),
            ],
        ]);

        $this->context->controller->registerJavascript(
            'zipysearch-widget',
            self::API_URL . '/widget/zipysearch.min.js',
            ['server' => 'remote', 'position' => 'bottom', 'priority' => 200]
        );
    }

    public function hookDisplayHeader($params)
    {
        $this->context->smarty->assign('zipysearch_injected', true);
        return $this->getInitHtml();
    }

    public function hookDisplayBeforeBodyClosingTag($params)
    {
        // Fallback pour les thèmes mobiles qui n'appellent pas displayHeader
        if (!empty($this->context->smarty->getTemplateVars('zipysearch_injected'))) {
            return '';
        }
        return $this->getInitHtml();
    }

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

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit_zipysearch')) {
            $tenantSlug = Tools::getValue('tenant_slug');
            $apiKey = Tools::getValue('api_key');

            Configuration::updateValue('ZIPYSEARCH_TENANT_SLUG', $tenantSlug);
            Configuration::updateValue('ZIPYSEARCH_API_KEY', $apiKey);
            Configuration::updateValue('ZIPYSEARCH_WIDGET_ENABLED', (int) Tools::getValue('widget_enabled'));
            Configuration::updateValue('ZIPYSEARCH_INPUT_SELECTOR', html_entity_decode(Tools::getValue('input_selector'), ENT_QUOTES, 'UTF-8'));
            Configuration::updateValue('ZIPYSEARCH_CONVERSION_TRACKING', (int) Tools::getValue('conversion_tracking'));
            Configuration::updateValue('ZIPYSEARCH_DEBUG_MODE', (int) Tools::getValue('debug_mode'));

            if ($tenantSlug && $apiKey) {
                $syncResult = $this->syncWithZipySearch($tenantSlug, $apiKey);
                if ($syncResult['success']) {
                    $output .= $this->displayConfirmation($this->l('Configuration saved and synchronized with ZipySearch'));
                } else {
                    $output .= $this->displayConfirmation($this->l('Configuration saved'));
                    $output .= $this->displayWarning($this->l('Synchronization with ZipySearch failed: ') . $syncResult['error']);
                }
            } else {
                $output .= $this->displayConfirmation($this->l('Configuration saved'));
            }
        }

        if (Tools::isSubmit('regenerate_token')) {
            Configuration::updateValue('ZIPYSEARCH_EXPORT_TOKEN', bin2hex(random_bytes(32)));
            $output .= $this->displayConfirmation($this->l('Token regenerated'));

            $tenantSlug = Configuration::get('ZIPYSEARCH_TENANT_SLUG');
            $apiKey = Configuration::get('ZIPYSEARCH_API_KEY');
            if ($tenantSlug && $apiKey) {
                $this->syncWithZipySearch($tenantSlug, $apiKey);
            }
        }

        return $output . $this->renderInstructions() . $this->renderForm();
    }

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
            'cronEnabled' => true,
            'cronFrequency' => 'daily',
            'cronTime' => '03:00',
            'triggerImport' => true,
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
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
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
            return ['success' => false, 'error' => $responseData['error'] ?? 'HTTP error ' . $httpCode];
        }

        return ['success' => true];
    }

    private function renderInstructions()
    {
        return $this->display(__FILE__, 'views/templates/admin/instructions.tpl');
    }

    private function renderForm()
    {
        $exportUrl = $this->context->link->getModuleLink('zipysearch', 'export', ['token' => Configuration::get('ZIPYSEARCH_EXPORT_TOKEN')]);

        $this->context->smarty->assign([
            'zipysearch_export_url' => $exportUrl,
        ]);
        $exportUrlHtml = $this->display(__FILE__, 'views/templates/admin/export_url.tpl');

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('ZipySearch Configuration'),
                    'icon' => 'icon-search',
                ],
                'input' => [
                    [
                        'type' => 'html',
                        'label' => '',
                        'name' => 'help_info',
                        'html_content' => $this->display(__FILE__, 'views/templates/admin/help_info.tpl'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('ZipySearch Account ID'),
                        'name' => 'tenant_slug',
                        'desc' => $this->l('Your unique account identifier (e.g., my-store)'),
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'name' => 'api_key',
                        'desc' => $this->l('Enables automatic configuration of the products export URL'),
                        'required' => true,
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->l('Products Export URL'),
                        'name' => 'export_url_html',
                        'html_content' => $exportUrlHtml,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable widget'),
                        'name' => 'widget_enabled',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Search input CSS selector'),
                        'name' => 'input_selector',
                        'desc' => $this->l('Default: input[name="s"]'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Track conversions'),
                        'name' => 'conversion_tracking',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'conv_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'conv_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Debug mode'),
                        'name' => 'debug_mode',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'debug_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'debug_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
                'buttons' => [
                    [
                        'type' => 'submit',
                        'name' => 'regenerate_token',
                        'title' => $this->l('Regenerate token'),
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
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
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
