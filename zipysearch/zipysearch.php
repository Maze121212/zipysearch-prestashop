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

        $this->context->smarty->assign([
            'zipysearch_api_url' => self::API_URL,
            'zipysearch_tenant' => $tenant,
            'zipysearch_input_selector' => Configuration::get('ZIPYSEARCH_INPUT_SELECTOR') ?: 'input[name="s"]',
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
            Configuration::updateValue('ZIPYSEARCH_TENANT_SLUG', Tools::getValue('tenant_slug'));
            Configuration::updateValue('ZIPYSEARCH_WIDGET_ENABLED', (int)Tools::getValue('widget_enabled'));
            Configuration::updateValue('ZIPYSEARCH_INPUT_SELECTOR', Tools::getValue('input_selector'));
            Configuration::updateValue('ZIPYSEARCH_CONVERSION_TRACKING', (int)Tools::getValue('conversion_tracking'));
            Configuration::updateValue('ZIPYSEARCH_DEBUG_MODE', (int)Tools::getValue('debug_mode'));
            $output .= $this->displayConfirmation($this->l('Configuration sauvegardee'));
        }

        if (Tools::isSubmit('regenerate_token')) {
            Configuration::updateValue('ZIPYSEARCH_EXPORT_TOKEN', bin2hex(random_bytes(32)));
            $output .= $this->displayConfirmation($this->l('Token regenere'));
        }

        return $output . $this->renderInstructions() . $this->renderForm();
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
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuration ZipySearch'),
                    'icon' => 'icon-search',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('ID de compte ZipySearch'),
                        'name' => 'tenant_slug',
                        'desc' => $this->l('Disponible dans votre espace ZipySearch (Dashboard ou Profil)'),
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Token d\'export'),
                        'name' => 'export_token',
                        'readonly' => true,
                        'desc' => $this->l('URL du flux CSV: ') . $this->context->link->getModuleLink('zipysearch', 'export', ['token' => Configuration::get('ZIPYSEARCH_EXPORT_TOKEN')]),
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
            'export_token' => Configuration::get('ZIPYSEARCH_EXPORT_TOKEN'),
            'widget_enabled' => Configuration::get('ZIPYSEARCH_WIDGET_ENABLED'),
            'input_selector' => Configuration::get('ZIPYSEARCH_INPUT_SELECTOR'),
            'conversion_tracking' => Configuration::get('ZIPYSEARCH_CONVERSION_TRACKING'),
            'debug_mode' => Configuration::get('ZIPYSEARCH_DEBUG_MODE'),
        ];

        return $helper->generateForm([$fields_form]);
    }
}
