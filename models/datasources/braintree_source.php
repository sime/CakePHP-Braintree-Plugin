<?php
/**
 * BraintreeSource DataSource File
 *
 * Copyright (c) 2010 Anthony Putignano
 *
 * Distributed under the terms of the MIT License.
 * Redistributions of files must retain the above copyright notice.
 *
 * PHP version 5.2
 * CakePHP version 1.3
 *
 * @package    braintree
 * @subpackage braintree.models.datasources
 * @copyright  2010 Anthony Putignano <anthonyp@xonatek.com>
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link       http://github.com/anthonyp/braintree
 */

/**
 * BraintreeSource DataSource Class
 *
 * @package    braintree
 * @subpackage braintree.models.datasources
 */
App::import('Vendor', 'Braintree.Braintree');
class BraintreeSource extends DataSource {

/**
 * The description of this data source
 *
 * @var string
 */
	public $description = 'Braintree DataSource';
	
/**
 * Generates errors as a result of a read() attempt
 *
 * @param	object	$model
 * @param	array	$queryData
 * @param	array	$options	Options include:
 * 								- 'is_count' bool - Whether or not the current query is attempting a COUNT()
 * @return	bool
 */
	private function _readErrors (&$model, $queryData = array(), $options=array()) {
		
		extract($queryData);
		extract(array_merge(
			array(
				'is_count' => false
			),
			$options
		));
		
		if (!empty($conditions) && !is_array($conditions)) {
			$this->showError(__('Conditions must be in array format', true));
			return false;
		}
		
		if (
			(
				!empty($conditions) && 
				count($conditions) > 1
			) || 
			(
				!empty($conditions) && 
				count($conditions == 1) && 
				empty($conditions[$model->alias . '.' . $model->primaryKey])
			)
		) {
			$this->showError(__('The only supported condition is ' . $model->alias . '.' . $model->primaryKey, true));
			return false;
		}
		
		if (
			!empty($conditions) && 
			$limit > 1
		) {
			$this->showError(__('Conditions cannot be used to search all customers', true));
			return false;
		}
		
		if (
			!empty($fields) && 
			!$is_count
		) {
			$this->showError(__('Fields are not supported', true));
			return false;
		}
		
		if (!empty($joins)) {
			$this->showError(__('Joins are not supported', true));
			return false;
		}
		
		if ($limit > 1 || $page > 1 || !empty($offset)) {
			$this->showError(__('Pagination is not supported', true));
			return false;
		}
		
		if (!empty($group)) {
			$this->showError(__('Group is not supported', true));
			return false;
		}
		
		return true;
		
	}
	
/**
 * Properly formats an array of information to be saved to Braintree's API
 *
 * @param	array	$fields
 * @param	array	$values
 * @return	array
 */
	private function _createSaveArray ($fields, $values) {
		
		$return = array();
		foreach ($fields as $key => $field) {
			$return[Inflector::variable($field)] = isset($values[$key]) ? $values[$key] : '';
		}
		
		return $return;
		
	}
	
/**
 * Get the 'entity' of the model implementing this DataSource
 * For example, if BraintreeRemoteTransaction is implementing this DataSource, the entity is Transaction
 *
 * @param	object	$model
 * @return	string
 */
	private function _getModelEntity (&$model) {
		
		return str_replace(array('BraintreeRemote'), '', $model->name);
		
	}

/**
 * Creates a new record via the API
 *
 * @param	object	$model
 * @param	array 	$fields
 * @param 	array 	$values
 * @return	bool
 */
	public function create(&$model, $fields = null, $values = null) {
		
		$to_save = $this->_createSaveArray($fields, $values);
		
		if (!empty($model->id) && empty($return[$model->primaryKey])) {
			$to_save = array_merge(
				array(
					$model->primaryKey => $model->id
				),
				$to_save
			);
		}
		
		$entity = $this->_getModelEntity($model);
		
		try {
			switch ($entity) {
				case 'Customer':
					$result = Braintree_Customer::create($to_save);
					if (!$result->success) {
						$this->showError($result->message);
						return false;
					}
					$id = $result->customer->id;
					break;
				case 'Transaction':
					unset($to_save['id']);
					if (
						!empty($to_save['type']) && 
						$to_save['type'] == 'credit'
					) {
						if (empty($to_save['braintreeTransactionId'])) {
							$this->showError(__('A refundable transaction ID must be provided.', true));
							return false;
						}
						$transaction = $this->read($model, array(
							'conditions' => array(
								$model->alias . '.' . $model->primaryKey => $to_save['braintreeTransactionId']
							)
						));
						if (empty($transaction)) {
							$this->showError(__('The transaction attempting to be refunded could not be found.', true));
							return false;
						}
						if ($transaction[0][$model->alias]['type'] == 'credit') {
							$this->showError(__('The transaction attempting to be refunded is a credit, not a sale.', true));
							return false;
						}
						$result = Braintree_Transaction::refund($to_save['braintreeTransactionId'], $to_save['amount']);
					} else { // it's a sale
						unset(
							$to_save['type'],
							$to_save['braintreeTransactionId']
						);
						$result = Braintree_Transaction::sale(array_merge(
							array(
								'options' => array(
									'submitForSettlement' => true
								)
							),
							$to_save
						));
					}
					if (!$result->success) {
						$this->showError($result->message);
						return false;
					}
					$id = $result->transaction->id;
					break;
				case 'CreditCard':
					return false;
					break;
				case 'Address':
					return false;
					break;
				default:
					$result = false;
					break;
			}
		} catch (Exception $e) {
			return false;
		}
		
		$model->setInsertID($id);
		$model->id = $id;
		
		return true;
		
	}
	
/**
 * Updates an existing record via the API
 *
 * @param	object	$model
 * @param	array 	$fields
 * @param 	array 	$values
 * @return	bool
 */
	public function update(&$model, $fields = null, $values = null) {
		
		$to_save = $this->_createSaveArray($fields, $values);
		
		if (!empty($to_save['id'])) {
			$model->id = $to_save['id'];
			unset($to_save['id']);
		}
		
		if (empty($model->id)) {
			false;
		}
		
		$entity = $this->_getModelEntity($model);
		
		try {
			switch ($entity) {
				case 'Customer':
					$result = Braintree_Customer::update($model->id, $to_save);
					break;
				case 'Transaction':
					$transaction = $this->read($model, array(
						'conditions' => array(
							$model->alias . '.' . $model->primaryKey => $model->id
						)
					));
					if (empty($transaction)) {
						return false;
					}
					if (!empty($to_save['status']) && $to_save['status'] == 'voided') {
						if (
							$transaction[0][$model->alias]['status'] != 'authorized' &&
							$transaction[0][$model->alias]['status'] != 'submitted_for_settlement'
						) {
							$this->showError(__('A transaction can only be VOIDED when the status is AUTHORIZED or SUBMITTED FOR SETTLEMENT.', true));
							return false;
						}
						$result = Braintree_Transaction::void($model->id);
						if (!$result->success) {
							$this->showError($result->message);
							return false;
						}
					} else {
						$this->showError(__('The only update that can be made to a transaction is a VOID.', true));
						return false;
					}
					break;
				case 'CreditCard':
					return false;
					break;
				case 'Address':
					return false;
					break;
				default:
					$result = false;
					break;
			}
		} catch (Exception $e) {
			return false;
		}
		
		$success = $result->success;
		
		if (!$success) {
			return false;
		}
		
		return $success;
		
	}

/**
 * Reads from the API
 *
 * @param	object	$model
 * @param	array 	$queryData
 * @return 	array
 */
	public function read (&$model, $queryData = array()) {
		
		$queryData = array_merge(
			array(
				'conditions' => null,
				'fields' => null,
				'joins' => array(),
				'limit' => 1,
				'offset' => null,
				'order' => array(0 => null),
				'page' => 1,
				'group' => null,
				'callbacks' => 1,
				'contain' => false,
				'recursive' => -1
			),
			$queryData
		);
		
		extract($queryData);
		
		if (!empty($fields) && is_string($fields) && $fields == 'count') {
			$is_count = true;
		} else {
			$is_count = false;
		}
		
		if (!$this->_readErrors($model, $queryData, array('is_count' => $is_count))) {
			return false;
		}
		
		if (
			!empty($conditions[$model->alias . '.' . $model->primaryKey]) && 
			(
				$limit == 1 || 
				(
					empty($limit) && 
					$is_count
				)
			)
		) {
			
			$entity = $this->_getModelEntity($model);
			
			try {
				switch ($entity) {
					case 'Customer':
						$customer = Braintree_Customer::find($conditions[$model->alias . '.' . $model->primaryKey]);
						$result = array(
							0 => array(
								$model->alias => array(
									'id' => $customer->id,
									'first_name' => $customer->firstName,
									'last_name' => $customer->lastName,
									'company' => $customer->company,
									'email' => $customer->email,
									'phone' => $customer->phone,
									'fax' => $customer->fax,
									'website' => $customer->website,
									'created' => $customer->createdAt->format('Y-m-d H:i:s'),
									'modified' => $customer->updatedAt->format('Y-m-d H:i:s')
								)
							)
						);
						break;
					case 'Transaction':
						$transaction = Braintree_Transaction::find($conditions[$model->alias . '.' . $model->primaryKey]);
						$result = array(
							0 => array(
								$model->alias => array(
									'id' => $transaction->id,
									'customer_id' => $transaction->customer['id'],
									'payment_method_token' => $transaction->creditCard['token'],
									'type' => $transaction->type,
									'amount' => $transaction->amount,
									'status' => $transaction->status,
									'created' => $transaction->createdAt->format('Y-m-d H:i:s'),
									'modified' => $transaction->updatedAt->format('Y-m-d H:i:s')
								)
							)
						);
						$result[0][$model->alias . 'Status'] = array();
						$count = 0;
						foreach ($transaction->statusHistory as $status) {
							$result[0][$model->alias . 'Status'][$count] = array(
								'status' => $status->status,
								'amount' => $status->amount,
								'user' => $status->user,
								'transaction_source' => $status->transactionSource,
								'created' => $status->timestamp->format('Y-m-d H:i:s')
							);
							$count++;
						}
						break;
					case 'CreditCard':
						$credit_card = Braintree_CreditCard::find($conditions[$model->alias . '.' . $model->primaryKey]);
						$result = array(
							0 => array(
								$model->alias => array(
									'token' => $credit_card->token,
									'customer_id' => $credit_card->customerId,
									'cardholder_name' => $credit_card->cardholderName,
									'card_type' => $credit_card->cardType,
									'masked_number' => $credit_card->maskedNumber,
									'expiration_date' => date('Y-m', strtotime($credit_card->expirationDate)) . '-01',
									'is_default' => $credit_card->default,
									'created' => $credit_card->createdAt->format('Y-m-d H:i:s'),
									'modified' => $credit_card->updatedAt->format('Y-m-d H:i:s')
								)
							)
						);
						break;
					case 'Address':
						$exploded = explode('|', $conditions[$model->alias . '.' . $model->primaryKey]);
						if (count($exploded) != 2) {
							return false;
						}
						list($customer_id, $address_id) = $exploded;
						$address = Braintree_Address::find($customer_id, $address_id);
						$result = array(
							0 => array(
								$model->alias => array(
									'id' => $address->customerId . '|' . $address->id,
									'first_name' => $address->firstName,
									'last_name' => $address->lastName,
									'company' => $address->company,
									'street_address' => $address->streetAddress,
									'extended_address' => $address->extendedAddress,
									'locality' => $address->locality,
									'region' => $address->region,
									'postal_code' => $address->postalCode,
									'country_code_alpha_2' => $address->countryCodeAlpha2,
									'country_code_alpha_3' => $address->countryCodeAlpha3,
									'country_code_numeric' => $address->countryCodeNumeric,
									'country_name' => $address->countryName,
									'created' => $address->createdAt->format('Y-m-d H:i:s'),
									'modified' => $address->updatedAt->format('Y-m-d H:i:s')
								)
							)
						);
						break;
					default:
						$result = false;
						break;
				}
			} catch (Exception $e) {
				$result = false;
			}
			
			if ($is_count) {
				return array(
					0 => array(
						0 => array(
							'count' => $result ? 1 : 0
						)
					)
				);
			}
			
			return $result;
			
		}
		
		if (empty($conditions)) {
			try {
				$all_customers = Braintree_Customer::all();
			} catch (Exception $e) {
				return array();
			}
			$return = array();
			$count = 0;
			foreach ($all_customers->_ids as $id) {
				$return[$count][$model->alias]['id'] = $id;
				$count++;
			}
			return $return;
		}
		
	}
	
/**
 * Deletes a record via the API
 *
 * @param	object	$model
 * @param	string	$id
 * @return	bool
 */
	public function delete (&$model, $id = null) {
		
		if (empty($id)) {
			$id = $model->id;
		}
		
		if (empty($id)) {
			$this->showError(__($model->alias . '.' . $model->primaryKey . ' must be set in order to delete', true));
		}
		
		$entity = $this->_getModelEntity($model);
		
		try {
			switch ($entity) {
				case 'Customer':
					Braintree_Customer::delete($id);
					break;
				case 'Transaction':
					$this->showError(__('Transactions cannot be deleted', true));
					return false;
					break;
				case 'CreditCard':
					Braintree_CreditCard::delete($id);
					break;
				case 'Address':
					$exploded = explode('|', $id);
					if (count($exploded) != 2) {
						return false;
					}
					list($customer_id, $address_id) = $exploded;
					Braintree_Address::delete($customer_id, $address_id);
					break;
				default:
					$result = false;
					break;
			}
		} catch (Exception $e) {
			return false;
		}
		
		return true;
		
	}
	
/**
 * An overwrite of the calculate() method to get it to play nice with an API-based DataSource
 *
 * @param	object	$model
 * @return	string
 */
	public function calculate(&$model) {
		return 'count';
	}
	
/**
 * Shows errors based on debug level
 *
 * @param	object	$model
 * @return	string
 */
	public function showError($error) {
		
		if (Configure::read('debug') > 0) {
			trigger_error($error, E_USER_WARNING);
		} else {
			$this->log($error);
		}
		
	}

}
?>