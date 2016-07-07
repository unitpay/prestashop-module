<?php
if (!defined('_PS_VERSION_'))
    exit;

class Unitpay extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'unitpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Юнитмобайл';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Модуль UnitPay');
        $this->description = $this->l('Модуль для подключения платежной системы UnitPay');
        $this->confirmUninstall = $this->l('Вы уверены что хотите деинсталлировать модуль?');

    }
    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install() ||
            !$this->registerHook('displayPayment') ||
            !$this->registerHook('displayHeader') ||
            !Configuration::updateValue('UNIT_OS_NEEDPAY', 800)||
            !Configuration::updateValue('UNIT_OS_PAYED', 801)||
            !Configuration::updateValue('UNIT_OS_ERROR_PAY', 802)||
            !Configuration::updateValue('UNIT_SECRET_KEY', '')||
            !Configuration::updateValue('UNIT_PUBLIC_KEY', '')
        )
            return false;


        $os = new OrderState((int)Configuration::get('UNIT_OS_NEEDPAY'));
        $os->id = Configuration::get('UNIT_OS_NEEDPAY');
        $os->force_id = true;
        $os->name = $this->multiLangField('UnitPay Ожидает подтверждения платежа');
        $os->color = '#8A2BE2';
        $os->module_name = $this->name;
        $os->paid = false;
        $os->logable = false;
        $os->shipped = false;
        $os->delivery = false;
        $os->add();

        $os = new OrderState((int)Configuration::get('UNIT_OS_PAYED'));
        $os->id = Configuration::get('UNIT_OS_PAYED');
        $os->force_id = true;
        $os->name = $this->multiLangField('UnitPay Платеж принят');
        $os->color = '#32CD32';
        $os->module_name = $this->name;
        $os->paid = true;
        $os->logable = false;
        $os->shipped = false;
        $os->delivery = false;
        $os->add();

        $os = new OrderState((int)Configuration::get('UNIT_OS_ERROR_PAY'));
        $os->id = Configuration::get('UNIT_OS_ERROR_PAY');
        $os->force_id = true;
        $os->name = $this->multiLangField('UnitPay Ошибка оплаты');
        $os->color = '#DC143C';
        $os->module_name = $this->name;
        $os->paid = false;
        $os->logable = false;
        $os->shipped = false;
        $os->delivery = false;
        $os->add();

        return true;
    }

    public function uninstall()
    {
        $os = new OrderState((int)Configuration::get('UNIT_OS_NEEDPAY'));
        $os->delete();

        $os = new OrderState((int)Configuration::get('UNIT_OS_PAYED'));
        $os->delete();

        $os = new OrderState((int)Configuration::get('UNIT_OS_ERROR_PAY'));
        $os->delete();


        if (!parent::uninstall() ||
            !Configuration::deleteByName('UNIT_OS_NEEDPAY')||
            !Configuration::deleteByName('UNIT_OS_PAYED')||
            !Configuration::deleteByName('UNIT_OS_ERROR_PAY')||
            !Configuration::deleteByName('UNIT_SECRET_KEY')||
            !Configuration::deleteByName('UNIT_PUBLIC_KEY')
        )
            return false;

        return true;
    }



    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit'))
        {
            if (!Tools::getValue('UNIT_SECRET_KEY'))
                $this->_postErrors[] = $this->l('Необходимо ввести SECRET KEY');
            elseif (!Tools::getValue('UNIT_PUBLIC_KEY'))
                $this->_postErrors[] = $this->l('Необходимо ввести PUBLIC KEY');
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit'))
        {
            Configuration::updateValue('UNIT_SECRET_KEY', Tools::getValue('UNIT_SECRET_KEY'));
            Configuration::updateValue('UNIT_PUBLIC_KEY', Tools::getValue('UNIT_PUBLIC_KEY'));
        }
        $this->_html .= $this->displayConfirmation($this->l('Настройки сохранены'));
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit'))
        {
            $this->_postValidation();
            if (!count($this->_postErrors))
                $this->_postProcess();
            else
                foreach ($this->_postErrors as $err)
                    $this->_html .= $this->displayError($err);
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('settings')
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('SECRET KEY'),
                        'desc' => "Скопируйте SECRET KEY со страницы проекта в системе Unitpay",
                        'name' => 'UNIT_SECRET_KEY',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('PUBLIC KEY'),
                        'desc' => "Скопируйте PUBLIC KEY со страницы проекта в системе Unitpay",
                        'name' => 'UNIT_PUBLIC_KEY',
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'UNIT_SECRET_KEY' => Tools::getValue('UNIT_SECRET_KEY', Configuration::get('UNIT_SECRET_KEY')),
            'UNIT_PUBLIC_KEY' => Tools::getValue('UNIT_PUBLIC_KEY', Configuration::get('UNIT_PUBLIC_KEY')),
        );
    }

    public function hookdisplayPayment()
    {
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookdisplayHeader()
    {
        $this->context->controller->addCSS($this->_path.'/views/css/main.css');
    }

    public function multiLangField($str)
    {
        $languages = Language::getLanguages(false);
        $data = array();
        foreach ($languages as $lang) {
            $data[$lang['id_lang']] = $str;
        }

        return $data;
    }
}



