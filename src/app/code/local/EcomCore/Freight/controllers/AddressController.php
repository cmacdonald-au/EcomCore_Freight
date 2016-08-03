<?php
/**
 * EcomCore Freight Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 * @author     Thai Phan
 * @copyright  Copyright (c) 2014 EcomCore Pty. Ltd. (http://www.ecomcore.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Controller for address validation requests
 *
 * @category   EcomCore
 * @package    EcomCore_Freight
 */
class EcomCore_Freight_AddressController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var EcomCore_Freight_Model_Address_Interface
     */
    protected $client;

    protected function _construct()
    {
        $model = Mage::getStoreConfig('ecomcore_freight/address_validation/backend');
        $this->setClient($model);
    }

    /**
     * Set the address validation backend client.
     *
     * @param string $client The model alias of the backend client
     */
    public function setClient($client)
    {
        $this->client = Mage::getModel($client);
    }

    /**
     * Sends a request to the address validation backend to validate the address
     * the customer provided on the checkout page.
     */
    public function validateAction()
    {
        // Check for a valid POST request
        if (!$this->getRequest()->isPost()) {
            // Return a "405 Method Not Allowed" response
            $this->getResponse()->setHttpResponseCode(405);
            return;
        }

        $request = $this->getRequest();
        if ($request->getPost('billing')) {
            $data = $request->getPost('billing');
        } else {
            $data = $request->getPost('shipping', array());
        }

        // Check that all of the required fields are present
        if (empty($data) || !isset($data['country_id'], $data['region_id'], $data['street'], $data['city'], $data['postcode'])) {
            // Return a "400 Bad Request" response
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        $country = Mage::getModel('directory/country')->load($data['country_id'])->getName();
        $region = Mage::getModel('directory/region')->load($data['region_id'])->getCode();

        $result = $this->client->validateAddress(
            $data['street'],
            $region,
            $data['city'],
            $data['postcode'],
            $country
        );

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}
