<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2019, EllisLab Corp. (https://ellislab.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

namespace EllisLab\Addons\FluidField\Model;

use EllisLab\ExpressionEngine\Service\Model\Model;
use EllisLab\ExpressionEngine\Model\Content\FieldData;

/**
 * ExpressionEngine Fluid Field Model
 */
class FluidField extends Model {

	protected static $_primary_key = 'id';
	protected static $_table_name = 'fluid_field_data';

	protected static $_typed_columns = array(
		'fluid_field_id'      => 'int',
		'entry_id'      => 'int',
		'field_id'      => 'int',
		'field_data_id' => 'int',
		'order'         => 'int',
	);

	protected static $_relationships = array(
		'ChannelEntry' => array(
			'type' => 'belongsTo',
			'model' => 'ee:ChannelEntry',
			'weak' => TRUE,
			'inverse' => array(
				'name' => 'FluidField',
				'type' => 'hasMany',
				'weak' => TRUE
			)
		),
		'ChannelField' => array(
			'type' => 'belongsTo',
			'model' => 'ee:ChannelField',
			'weak' => TRUE,
			'inverse' => array(
				'name' => 'FluidField',
				'type' => 'hasMany',
				'weak' => TRUE
			)
		),
		'FieldField' => array(
			'type' => 'belongsTo',
			'from_key' => 'fluid_field_id',
			'to_key'   => 'field_id',
			'model' => 'ee:ChannelField',
			'weak' => TRUE,
			'inverse' => array(
				'name' => 'FluidField',
				'type' => 'hasOne',
				'weak' => TRUE
			)
		)
	);

	protected static $_events = array(
		'afterDelete'
	);

	protected $id;
	protected $fluid_field_id;
	protected $entry_id;
	protected $field_id;
	protected $field_data_id;
	protected $order;

	public function onAfterDelete()
	{
		$table = 'channel_data_field_' . $this->field_id;

		if (ee()->db->table_exists($table))
		{
			ee()->db->where('id', $this->field_data_id);
			ee()->db->delete($table);
		}
	}

	protected function getSessionCacheKey()
	{
		return "ChannelField/{$this->field_id}/Data/{$this->field_data_id}";
	}

	public function setFieldData(array $data)
	{
		$field_data = ee('Model')->make('FieldData')->forField($this->ChannelField);
		$field_data->set($data);
		ee()->session->set_cache(__CLASS__, $this->getSessionCacheKey(), $field_data);

		return $field_data;
	}

    /**
     * @return array
     */
	public function fetchFieldData()
	{
		if (ee()->extensions->active_hook('fluid_field_get_field_data') === TRUE)
		{
			$rows = ee()->extensions->call('fluid_field_get_field_data', $this->field_id, $this->field_data_id);
		}
		else
		{
			$rows = ee('db')
				->where('id', $this->field_data_id)
				->get('channel_data_field_' . $this->field_id)
				->result_array();
		}

		if ( ! empty($rows))
		{
			return $rows[0];
		}

		return [];
	}

	/**
	 * @param int $entry_id
	 * @param int $fluid_field_id
	 * @return array
	 */
	public function fetchAllFieldData($entry_id, $fluid_field_id)
	{
		if (ee()->extensions->active_hook('fluid_field_get_all_data') === TRUE)
		{
			$data = ee()->extensions->call('fluid_field_get_all_data', $entry_id, $fluid_field_id);
		}
		else
		{
			$cache_key = "FluidField/{$fluid_field_id}/{$entry_id}";

			if (($data = ee()->session->cache("FluidField", $cache_key, FALSE)) === FALSE)
			{
				$data = $this->getModelFacade()->get('fluid_field:FluidField')
					->with('ChannelField')
					->filter('fluid_field_id', $fluid_field_id)
					->filter('entry_id', $entry_id)
					->order('order')
					->all();
			}

			ee()->session->set_cache("FluidField", $cache_key, $data);
		}

		return $data;
	}

	/**
	 * @param array $entry_ids
	 * @param array $fluid_field_ids
	 * @return array
	 */
	public function fetchAllFluidData(array $entry_ids = [], array $fluid_field_ids = [])
	{
		if (ee()->extensions->active_hook('fluid_field_get_all_fluid_data') === TRUE)
		{
			$data = ee()->extensions->call('fluid_field_get_all_fluid_data', $entry_ids, $entry_ids);
		}
		else
		{
			$cache_key = 'FluidField/'. md5(serialize($fluid_field_ids)) .'/'. md5(serialize($entry_ids));

			if (($data = ee()->session->cache("FluidField", $cache_key, FALSE)) === FALSE)
			{
				$data = ee('Model')->get('fluid_field:FluidField')
					->with('ChannelField')
					->filter('fluid_field_id', 'IN', $fluid_field_ids)
					->filter('entry_id', 'IN', $entry_ids)
					->order('fluid_field_id')
					->order('entry_id')
					->order('order')
					->all();

				// Since we store the data in the field's table, and each field has its
				// own table, we'll group our fluid field data by the field_id. This will
				// allow us to run one query per field, fetching all the data across
				// all the fluid fields & entries for each field.
				$fields = [];

				foreach ($data as $fluid_field)
				{
					if ( ! array_key_exists($fluid_field->field_id, $fields))
					{
						$fields[$fluid_field->field_id] = [];
					}

					$fields[$fluid_field->field_id][$fluid_field->field_data_id] = $fluid_field;
				}

				foreach ($fields as $field_id => $fluid_fields)
				{
					$field_data_ids = array_keys($fluid_fields);

					// Captain Obvious says: here we be gettin' the data, Arrrr!
					ee()->db->where_in('id', $field_data_ids);
					$rows = ee()->db->get('channel_data_field_' . $field_id)->result_array();

					foreach($rows as $row)
					{
						$fluid_fields[$row['id']]->setFieldData($row);
					}
				}
			}

			ee()->session->set_cache("FluidField", $cache_key, $data);
		}

		return $data;
	}


	public function getFieldData()
	{
		if (($field_data = ee()->session->cache(__CLASS__, $this->getSessionCacheKey(), FALSE)) === FALSE)
		{
			$field_data = $this->setFieldData($this->fetchFieldData());
		}

		return $field_data;
	}

	public function getField(FieldData $field_data = NULL)
	{
		$field = $this->ChannelField->getField();
		$field->setContentId($this->entry_id);

		$field_data = ($field_data) ?: $this->getFieldData();

		$field->setData($field_data->getProperty('field_id_' . $this->field_id));

		if ($field_data->getProperty('field_ft_' . $this->field_id) !== NULL)
		{
			$format = $field_data->getProperty('field_ft_' . $this->field_id);

			// Need to set this property because it will override the
			// format on successive calls to `getField()`
			$this->ChannelField->field_fmt = $format;
			$field->setFormat($format);
		}

		if ($field_data->getProperty('field_dt_' . $this->field_id) !== NULL)
		{
			$format = $field_data->getProperty('field_dt_' . $this->field_id);
			$field->setTimezone($format);
		}

		$field->setName('content');
		$field->setItem('fluid_field_data_id', $this->getId());

		return $field;
	}
}

// EOF
