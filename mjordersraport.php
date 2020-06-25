<?php
/**
 * Raporty o zamówieniach
 * @author MAGES Michał Jendraszczyk
 * @coptyright 2020
 */

class Mjordersraport extends Module
{
    public function __construct()
    {
        $this->name = 'mjordersraport';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'MAGES Michał Jendraszczyk';
        $this->displayName = $this->l('Raport dla zamówień');
        $this->description = $this->l('Generuje raport dla zamówień');
        $this->bootstrap = true;
        
        parent::__construct();
        $this->confirmUninstall = $this->l('Usunąć moduł?');
    }
    public function install()
    {
        return parent::install();
    }
    public function renderForm()
    {
        $payments = PaymentModule::getInstalledPaymentModules();
        $orderState = OrderState::getOrderStates($this->context->language->id);
        $fields_form = array();
        
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Raport dla zamówień'),
            ),
            'input' => array(
                array(
                    'type' => 'date',
                    'label' => $this->l('Od'),
                    'size' => '5',
                    'name' => $this->name.'_od',
                    'required' => true,
                ),
                array(
                    'type' => 'date',
                    'label' => $this->l('Do'),
                    'size' => '5',
                    'name' => $this->name.'_do',
                    'required' => true,
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Status'),
                    'name' => $this->name.'_status',
                    'required' => true,
                    'class' => 'form-control',
                    'options' => array(
                        'query' => $orderState,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Status płatności'),
                    'name' => $this->name.'_status_platnosci',
                    'required' => true,
                    'options' => array(
                        'query' => $payments,
                        'id' => 'id_module',
                        'name' => 'name'
                    )
                ),
            ),
            'buttons' => array(
                    'save' => array(
                    'title' => $this->l('Generuj raport'),
                    'name' => 'saveConfiguration',
                    'type' => 'submit',
                    'id' => $this->name.'_save',
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-save',
                ),
                )
            );

        $form = new HelperForm();
        $form->tpl_vars['fields_value'][$this->name.'_od'] = Tools::getValue($this->name.'_od', Configuration::get($this->name."_od"));
        $form->tpl_vars['fields_value'][$this->name.'_do'] = Tools::getValue($this->name.'_do', Configuration::get($this->name."_do"));
        $form->tpl_vars['fields_value'][$this->name.'_status'] = Tools::getValue($this->name.'_status', Configuration::get($this->name."_status"));
        $form->tpl_vars['fields_value'][$this->name.'_status_platnosci'] = Tools::getValue($this->name.'_status_platnosci', Configuration::get($this->name."_status_platnosci"));
        
        return $form->generateForm($fields_form);
    }
    public function getContent()
    {
        return $this->postProcess().$this->renderForm();
    }
    public function uninstall()
    {
        return parent::uninstall();
    }
    public function postProcess()
    {
        if (Tools::isSubmit('saveConfiguration'))
        {
            if ((!empty(Tools::getValue($this->name.'_od'))) && (!empty(Tools::getValue($this->name.'_do')))) {
                $orders = Order::getOrdersIdByDate(Tools::getValue($this->name.'_od'), Tools::getValue($this->name.'_do'));
                
//                print_r($orders);
//                exit();
                $specyfikacja = array();
                $specyfikacja[] = array("numer zamówienia", "data zamówienia", "status", "kwota zamówienia", "kwota dostawy", "ilość produktów", "miasto");
                foreach ($orders as $order) {
                    $o = new Order($order);
                    $c = new Customer($o->id_customer);
                    $a = new Address($o->id_address_delivery);
                    
                    $order_state = (new OrderState($o->current_state))->name[$this->context->language->id];
                    
                    $order_payment = $this->getModule(Tools::getValue($this->name.'_status_platnosci'));
                    if($order_payment == $o->module) {
                        if(Tools::getValue($this->name.'_status') == $o->current_state) {
                            // numer zamówienia / data zamówienia / status / kwota zamówienia / kwota dostawy / ilość produktów / miasto /
                            $specyfikacja[] = array($o->reference, $o->date_add, $order_state, $o->total_paid, $o->total_shipping, count($o->getProducts()), $a->city);
                        }
                    }
                }
                return $this->OrdersList($specyfikacja);
            } else {
                return $this->displayError($this->l('Wybierz zakres eksportu'));
            }
        }
    }
    
    public function OrdersList($orders_detail)
    {
        header('Content-Type: text/csv');
        header('Content-Type: application/force-download; charset=UTF-8');
        header('Cache-Control: no-store, no-cache');
        header('Content-Disposition: attachment; filename="raport_zamowien.csv"');

        $file = fopen('php://output', 'w');
        foreach ($orders_detail as $order) {
            fputcsv($file, $order);
        }
        fclose($file);
        exit();
    }
    private function getModule($id_module)
    {
        $sql = "SELECT * FROM "._DB_PREFIX_."module WHERE id_module = '".$id_module."'";
        
        if(count(DB::getInstance()->ExecuteS($sql, 1, 0)) > 0) {
            return DB::getInstance()->ExecuteS($sql, 1, 0)[0]['name'];
        } else {
            return false;
        }
    }
}