<?php

/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2014 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Model_Adyen_Data_OpenInvoiceDetailResult extends Adyen_Payment_Model_Adyen_Data_Abstract
{

    public $result;

    public function create($request)
    {
        $incrementId = $request->request->reference;

        //amaount negative
        $amount = (float)$request->request->amount->value / 100;
        $isRefund = ($amount <= 0) ? true : false;

        if (empty($incrementId)) {
            return false;
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        $currency = $order->getStoreCurrencyCode();
        $count = 1;
        $lines = array();
        foreach ($order->getItemsCollection() as $item) {
            //skip dummies
            if ($item->isDummy()) {
                continue;
            }

            $lines[] = Mage::getModel('adyen/adyen_data_invoiceRow')->create($item, $count, $order);
            $count++;
        }

        //discount cost
        if ($order->getDiscountAmount() > 0 || $order->getDiscountAmount() < 0) {
            $cost = new Varien_Object();
            $cost->setName(Mage::helper('adyen')->__('Total Discount'));
            $cost->setPrice($order->getDiscountAmount());
            $cost->setQtyOrdered(1);
            $lines[] = Mage::getModel('adyen/adyen_data_invoiceRow')->create($cost, $count, $order);
            $count++;
        }

        //shipping cost
        if ($order->getShippingAmount() > 0 || $order->getShippingTaxAmount() > 0) {
            $cost = new Varien_Object();
            $cost->setName($order->getShippingDescription());
            $cost->setPrice($order->getShippingAmount());
            $cost->setTaxAmount($order->getShippingTaxAmount());
            $cost->setQtyOrdered(1);
            $lines[] = Mage::getModel('adyen/adyen_data_invoiceRow')->create($cost, $count, $order);
            $count++;
        }

        if ($order->getPaymentFeeAmount() > 0) {
            $cost = new Varien_Object();
            $cost->setName(Mage::helper('adyen')->__('Payment Fee'));
            $cost->setPrice($order->getPaymentFeeAmount());
            $cost->setQtyOrdered(1);
            $lines[] = Mage::getModel('adyen/adyen_data_invoiceRow')->create($cost, $count, $order);
            $count++;
        }

        // Klarna wants tax cost provided in the lines of the products so overal tax cost is not needed anymore
//        $cost = new Varien_Object();
//        $cost->setName(Mage::helper('adyen')->__('Tax'));
//        $cost->setPrice($order->getTaxAmount());
//        $cost->setQtyOrdered(1);
//        $lines[] = Mage::getModel('adyen/adyen_data_invoiceRow')->create($cost, $count, $order);
//        $count++;

        /**
         * Refund line, heads up $lines is overwritten!
         */
        if ($isRefund === true) {
            $refundLine = $this->extractRefundLine($order, $amount);
            $lines = Mage::getModel('adyen/adyen_data_invoiceRow')->create($refundLine, $count, $order);
        }

        //all lines
        $InvoiceLine = Mage::getModel('adyen/adyen_data_invoiceLine')->create($lines);
        @$this->result->lines = $InvoiceLine;

        //debug
        Mage::log($this, null, 'openinvoice-response.log', true);

        return $this;
    }

    public function extractRefundLine($order, $amount)
    {
        $_extract = new Varien_Object();
        $_extract->setName('Refund / Correction');
        $_extract->setPrice($amount);
        $_extract->setTaxAmount(0);
        $_extract->setQtyOrdered(1);
        return $_extract;

    }

}
