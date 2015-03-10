<?php

namespace EllisLab\ExpressionEngine\Service\Model\Query;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Insert Query
 *
 * @package		ExpressionEngine
 * @subpackage	Model
 * @category	Service
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class Insert extends Update {

	protected $insert_id;

	public function run()
	{
		$object = $this->builder->getExisting();

		$object->emit('beforeSave');
		$object->emit('beforeInsert');

		$insert_id = $this->doWork($object);

		$object->emit('afterInsert');
		$object->emit('afterSave');

	}

	public function doWork($object)
	{
		$this->insert_id = NULL;

		parent::doWork($object);

		$object->setId($this->insert_id);

		return $this->insert_id;
	}

	/**
	 * Set insert id to the first one we get
	 */
	protected function setInsertId($id)
	{
		if ( ! isset($this->insert_id))
		{
			$this->insert_id = $id;
		}
	}

	protected function actOnGateway($gateway, $object)
	{
		$values = $gateway->getValues();
		$primary_key = $gateway->getPrimaryKey();

		if (isset($this->insert_id))
		{
			unset($values[$primary_key]);
		}
		else
		{
			$values[$primary_key] = $this->insert_id;
		}

		$query = $this->store
			->rawQuery()
			->set($gateway->getValues())
			->insert($gateway->getTableName());

		$this->setInsertId(
			$this->store->rawQuery()->insert_id()
		);
	}
}