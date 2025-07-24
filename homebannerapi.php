<?php
if (!defined('_PS_VERSION_')) exit;

class Homebannerapi extends Module
{
    public function __construct()
    {
        $this->name = 'homebannerapi';
        $this->tab = 'front_office_features';
        $this->version = '2.0.0';
        $this->author = 'i-creativi';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Home Banner API');
        $this->description = $this->l('Secure API for homepage banner with mandatory Bearer token authentication and centralized endpoint.');
    }

    public function install()
    {
        // Create cache directory
        $cacheDir = _PS_MODULE_DIR_.'homebannerapi/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // Create logs directory
        $logsDir = _PS_MODULE_DIR_.'homebannerapi/logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        return parent::install()
            && Configuration::updateValue('HBAPI_ENABLED', 1)
            && Configuration::updateValue('HBAPI_TOKEN', Tools::passwdGen(16))
            && Configuration::updateValue('HBAPI_SOURCE', 'iqit_elementor')
            && Configuration::updateValue('HBAPI_LOGGING', 0)
            && Configuration::updateValue('HBAPI_CUSTOM_BANNER', _PS_BASE_URL_ . '/img/cms/placeholder.jpg')
            && Configuration::updateValue('HBAPI_CUSTOM_BANNER_MOBILE', '')
            && $this->registerHook('actionClearCache');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('HBAPI_ENABLED')
            && Configuration::deleteByName('HBAPI_TOKEN')
            && Configuration::deleteByName('HBAPI_SOURCE')
            && Configuration::deleteByName('HBAPI_LOGGING')
            && Configuration::deleteByName('HBAPI_CUSTOM_BANNER')
            && Configuration::deleteByName('HBAPI_CUSTOM_BANNER_MOBILE');
    }

    public function getContent()
    {
        $this->_html = '';
        
        if (Tools::isSubmit('submitHBAPIModule')) {
            Configuration::updateValue('HBAPI_ENABLED', (int)Tools::getValue('HBAPI_ENABLED'));
            Configuration::updateValue('HBAPI_TOKEN', Tools::getValue('HBAPI_TOKEN'));
            Configuration::updateValue('HBAPI_SOURCE', Tools::getValue('HBAPI_SOURCE'));
            Configuration::updateValue('HBAPI_LOGGING', (int)Tools::getValue('HBAPI_LOGGING'));
            Configuration::updateValue('HBAPI_CUSTOM_BANNER', Tools::getValue('HBAPI_CUSTOM_BANNER'));
            Configuration::updateValue('HBAPI_CUSTOM_BANNER_MOBILE', Tools::getValue('HBAPI_CUSTOM_BANNER_MOBILE'));
            $this->clearApiCache();
            $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $this->_html . $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->submit_action = 'submitHBAPIModule';

        $helper->fields_value = [
            'HBAPI_ENABLED' => Configuration::get('HBAPI_ENABLED'),
            'HBAPI_TOKEN' => Configuration::get('HBAPI_TOKEN'),
            'HBAPI_SOURCE' => Configuration::get('HBAPI_SOURCE'),
            'HBAPI_LOGGING' => Configuration::get('HBAPI_LOGGING'),
            'HBAPI_CUSTOM_BANNER' => Configuration::get('HBAPI_CUSTOM_BANNER', _PS_BASE_URL_ . '/img/cms/placeholder.jpg'),
            'HBAPI_CUSTOM_BANNER_MOBILE' => Configuration::get('HBAPI_CUSTOM_BANNER_MOBILE', _PS_BASE_URL_ . '/img/cms/placeholder.jpg')
        ];

        return $helper->generateForm([[
            'form' => [
                'legend' => ['title' => $this->l('API Settings')],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable API'),
                        'name' => 'HBAPI_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API Token'),
                        'name' => 'HBAPI_TOKEN'
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Banner Source'),
                        'name' => 'HBAPI_SOURCE',
                        'options' => [
                            'query' => [
                                ['id' => 'iqit_elementor', 'name' => 'IqitElementor (Homepage)'],
                                ['id' => 'custom', 'name' => 'Static Banner']
                            ],
                            'id' => 'id',
                            'name' => 'name'
                        ]
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable API Logging'),
                        'name' => 'HBAPI_LOGGING',
                        'is_bool' => true,
                        'desc' => $this->l('Log all API calls with timestamp, IP, and response status'),
                        'values' => [
                            ['id' => 'logging_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'logging_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('URL Banner Personalizzato (Desktop)'),
                        'name' => 'HBAPI_CUSTOM_BANNER',
                        'desc' => $this->l('URL completo dell\'immagine banner per desktop (utilizzato per la fonte "Static Banner")'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('URL Banner Personalizzato (Mobile)'),
                        'name' => 'HBAPI_CUSTOM_BANNER_MOBILE',
                        'desc' => $this->l('URL completo dell\'immagine banner per mobile (utilizzato per la fonte "Static Banner")'),
                    ]
                ],
                'submit' => ['title' => $this->l('Save')]
            ]
        ]]);
    }

    private function clearApiCache()
    {
        $cacheFile = _PS_MODULE_DIR_ . 'homebannerapi/cache/banner.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
    
    public function hookActionClearCache($params)
    {
        $this->clearApiCache();
    }
}
