<?php
/**
 * *
 *  * PHP version 5
 *  *
 *  * LICENSE: This source file is subject to version 3.01 of the PHP license
 *  * that is available through the world-wide-web at the following URI:
 *  * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 *  * the PHP License and are unable to obtain it through the web, please
 *  * send a note to license@php.net so we can mail you a copy immediately.
 *  *
 *  * @category   CategoryName
 *  * @package    PackageName
 *  * @author     Ilya Voinov <ilya.voinov@yahoo.com>
 *  * @copyright  1997-2016 The PHP Group
 *  * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 *
 */

class Ivoinov_Report_Helper_Data extends Mage_Core_Helper_Abstract
{
    CONST XML_PATH_ORDER_REPORT_ENABLED            = 'ivoinov_report/order/enabled';
    CONST XML_PATH_ORDER_REPORT_TEMPLATE           = 'ivoinov_report/order/template';
    CONST XML_PATH_ORDER_REPORT_SEND_TO            = 'ivoinov_report/order/send_to';
    CONST XML_PATH_ORDER_REPORT_IS_ATTACH_CSV_FILE = 'ivoinov_report/order/is_attach_csv_file';

    /**
     * Send order report
     *
     * @throws Mage_Core_Exception
     */
    public function sendOrderReport()
    {
        if (Mage::getStoreConfigFlag(self::XML_PATH_ORDER_REPORT_ENABLED)) {
            $senderName = Mage::getStoreConfig('trans_email/ident_support/name');
            $senderEmail = Mage::getStoreConfig('trans_email/ident_support/email');
            $template = Mage::getStoreConfig(self::XML_PATH_ORDER_REPORT_TEMPLATE);
            $sendTo = explode(',', Mage::getStoreConfig(self::XML_PATH_ORDER_REPORT_SEND_TO));
            $vars = array('orderList' => $this->_getOrderList());
            if (count($sendTo) > 0) {
                /** @var Mage_Core_Model_Email_Template $emailTemplate */
                $emailTemplate = Mage::getModel('core/email_template');
                $emailTemplate->getMail()->addCc($sendTo);
                if (Mage::getStoreConfigFlag(self::XML_PATH_ORDER_REPORT_IS_ATTACH_CSV_FILE)) {
                    $this->_attachCSVFile($emailTemplate);
                }
                $emailTemplate->sendTransactional($template, array('name' => $senderName, 'email' => $senderEmail),
                    $sendTo[0], $sendTo[0], $vars);
            }
        }
    }

    /**
     * Return failed order list.
     *
     * @return string
     */
    protected function _getOrderList()
    {
        $orderList = '';
        $currentDate = Mage::getModel('core/date')->date('Y-m-d H:i:s');
        /** @var Mage_Sales_Model_Resource_Order_Collection $orderCollection */
        $orderCollection = Mage::getResourceModel('sales/order_collection');
        $orderCollection->addAttributeToFilter('is_send_to_wfl', 1);
        $orderCollection->addFieldToFilter(array('status', 'state'), array(
            array('neq' => 'complete'),
            array('neq' => 'complete'),
        ));
        $orderCollection->addAttributeToFilter('send_to_wfl_at', array('lteq' => $currentDate));
        /** @var Mage_Sales_Model_Order $order */
        foreach ($orderCollection as $order) {
            $orderList .= "<li>" . $order->getIncrementId() . "</li>";
        }

        return $orderList;
    }

    protected function _attachCSVFile(Mage_Core_Model_Email_Template $emailTemplate)
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, array('date', 'number_of_orders'));
        fputcsv($fp, array($this->_getCurrentDate(), $this->_getNumberOfOpenOrders()));
        rewind($fp);
        $body = fread($fp, 1048576);
        fclose($fp);
        $emailTemplate->getMail()
            ->createAttachment($body, Zend_Mime::TYPE_OCTETSTREAM, Zend_Mime::DISPOSITION_ATTACHMENT,
                Zend_Mime::ENCODING_BASE64, 'openOrders.csv');
    }

    /**
     * Return current day
     *
     * @return string
     */
    protected function _getCurrentDate()
    {
        return Mage::getModel('core/date')->date('d/m/Y');
    }

    /**
     * Return number of opened orders during last day.
     *
     * @return integer
     */
    protected function _getNumberOfOpenOrders()
    {
        /** @var Mage_Core_Model_Date $coreDate */
        $coreDate = Mage::getModel('core/date');
        $to = $coreDate->timestamp();
        $from = strtotime('-1 day', $to);
        /** @var Mage_Sales_Model_Resource_Order_Collection $ordersCollection */
        $ordersCollection = Mage::getResourceModel('sales/order_collection');
        $ordersCollection->addFieldToFilter('created_at', array(
            'from' => date('Y-m-d H:i:s', $from),
            'to'   => date('Y-m-d H:i:s', $to),
        ));

        return count($ordersCollection);
    }
}