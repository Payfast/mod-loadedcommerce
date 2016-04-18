<?php
/**
 *
 * Copyright (c) 2016 PayFast (Pty) Ltd
 *
 * LICENSE:
 *
 * This payment module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version.
 *
 * This payment module is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 *
 * @author     Brendon Posen
 * @copyright  2016 PayFast (Pty) Ltd
 * @license    http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

class PayFast extends lC_Addon
{
    /*
    * Class constructor
    */
    public function PayFast()
{
        global $lC_Language;
        /**
         * The addon type (category)
         * valid types; payment, shipping, themes, checkout, catalog, admin, reports, connectors, other
         */
        $this->_type = 'payment';
        /**
         * The addon class name
         */
        $this->_code = 'PayFast';
        /**
         * The addon title used in the addons store listing
         */
        $this->_title = $lC_Language->get( 'addon_payment_payfast_title' );
        /**
         * The addon description used in the addons store listing
         */
        $this->_description = $lC_Language->get( 'addon_payment_payfast_description' );
        /**
         * The developers name
         */
        $this->_author = 'PayFast';
        /**
         * The developers web address
         */
        $this->_authorWWW = 'http://www.payfast.co.za';
        /**
         * The addon version
         */
        $this->_version = '1.0.0';
        /**
         * The Loaded 7 core compatibility version
         */
        $this->_compatibility = '7.002.0.0'; // the addon is compatible with this core version and later
        /**
         * The base64 encoded addon image used in the addons store listing
         */
        $this->_thumbnail = lc_image(DIR_WS_CATALOG . 'addons/' . $this->_code . '/images/payfast.png', $this->_title);
        /**
         * The mobile capability of the addon
         */
        $this->_mobile_enabled = true;
        /**
         * The addon enable/disable switch
         */
        $this->_enabled = ( defined( 'ADDONS_PAYMENT_' . strtoupper( $this->_code ) . '_STATUS' ) && @constant( 'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_STATUS' ) == '1') ? true : false;
    }
    /**
     * Checks to see if the addon has been installed
     *
     * @access public
     * @return boolean
     */
    public function isInstalled()
    {
        return ( bool )defined( 'ADDONS_PAYMENT_' . strtoupper( $this->_code ) . '_STATUS' );
    }
    /**
     * Install the addon
     *
     * @access public
     * @return void
     */
    public function install()
    {
        global $lC_Database;

        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Enable AddOn', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_STATUS', '-1', 'Do you want to enable this addon?', '6', '0', 'lc_cfg_use_get_boolean_value', 'lc_cfg_set_boolean_value(array(1, -1))', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_MERCHANT_ID', '', 'Enter Your PayFast Merchant ID', '6', '1', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Key', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_MERCHANT_KEY', '', 'Enter Your PayFast Merchant ID', '6', '2', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Passphrase', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_PASSPHRASE', '', 'Only enter a passphrase if it is set on your PayFast account', '6', '2', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Pending Status', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_PROCESSING_STATUS_ID', '1', 'Set the Pending Notification status of orders made with this payment module', '6', '7', 'lc_cfg_use_get_order_status_title', 'lc_cfg_set_order_statuses_pull_down_menu', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Complete Status', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_ORDER_DEFAULT_STATUS_ID', '1', 'Set the status of orders made with this payment module', '6', '8', 'lc_cfg_use_get_order_status_title', 'lc_cfg_set_order_statuses_pull_down_menu', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Hold Status', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_ORDER_ONHOLD_STATUS_ID', '1', 'Set the status of <b>On Hold</b> orders made with this payment module', '6', '9', 'lc_cfg_use_get_order_status_title', 'lc_cfg_set_order_statuses_pull_down_menu', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Canceled Status', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_ORDER_CANCELED_STATUS_ID', '10', 'Set the status of <b>Canceled</b> orders made with this payment module', '6', '9', 'lc_cfg_use_get_order_status_title', 'lc_cfg_set_order_statuses_pull_down_menu', now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_SORT_ORDER', '100', 'Sort order of display. Lowest is displayed first.', '6', '11' , now())");
        $lC_Database->simpleQuery("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Sandbox Mode', 'ADDONS_PAYMENT_" . strtoupper($this->_code) . "_TEST_MODE', '-1', 'Set to \'Yes\' for sandbox test environment or set to \'No\' for production environment.', '6', '24', 'lc_cfg_use_get_boolean_value', 'lc_cfg_set_boolean_value(array(1, -1))', now())");

        $lC_Database->simpleQuery("ALTER TABLE " . TABLE_ORDERS . " CHANGE payment_method payment_method VARCHAR( 512 ) NOT NULL");

        // set ipn.php perms to 0755
        $path = DIR_FS_CATALOG . 'addons/PayFast/payfast_itn.php';
        chmod($path, 0755);
    }
    /**
     * Return the configuration parameter keys an an array
     *
     * @access public
     * @return array
     */
    public function getKeys()
    {
        if ( !isset( $this->_keys ) )
        {
            $this->_keys = array(
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_STATUS',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_MERCHANT_ID',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_MERCHANT_KEY',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_PASSPHRASE',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_PROCESSING_STATUS_ID',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_ORDER_DEFAULT_STATUS_ID',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_ORDER_ONHOLD_STATUS_ID',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_ORDER_CANCELED_STATUS_ID',
                'ADDONS_PAYMENT_' . strtoupper($this->_code) . '_TEST_MODE');
        }

        return $this->_keys;
    }
}
?>