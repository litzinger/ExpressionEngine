<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Api_channel_fields extends Api {

	var $field_types	= array();
	var $ft_paths		= array();
	var $settings		= array();

	var $ee_base_ft		= FALSE;

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function Api_channel_fields()
	{
		parent::Api();
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Set settings
	 *
	 * @access	public
	 */
	function set_settings($field_id, $settings)
	{
		if ( ! array_key_exists('field_name', $settings))
		{
			$settings['field_name'] = $field_id;
		}
		
		if ( ! array_key_exists($settings['field_type'], $this->field_types))
		{
			$this->field_types[$settings['field_type']] = $this->include_handler($settings['field_type']);
		}
		
		$this->settings[$field_id] = $settings;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Get settings
	 *
	 * @access	public
	 */
	function get_settings($field_id)
	{
		return isset($this->settings[$field_id]) ? $this->settings[$field_id] : array();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Fetch all fieldtypes
	 *
	 * @access	public
	 */
	function fetch_all_fieldtypes()
	{
		$this->EE->load->library('addons');
		
		$fts = $this->EE->addons->get_files('fieldtypes');
		
		foreach($fts as $key => $data)
		{
			$this->field_types[$key] = $this->include_handler($key);
			
			$opts = get_class_vars($data['class']);
			
			if (isset($opts['info'], $opts['info']['name']))
			{
				$fts[$key]['name'] = $opts['info']['name'];
			}
		}
		
		return $fts;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch defined custom fields
	 *
	 * @access	public
	 */
	function fetch_custom_channel_fields()
	{
		$this->EE->db->select('field_id, field_type, field_name, site_id');
		$query = $this->EE->db->get('channel_fields');
		
		$cfields = array();
		$dfields = array();
		$rfields = array();
		$pfields = array();
		
		foreach ($query->result_array() as $row)
		{
			if ( ! array_key_exists($row['field_type'], $this->field_types))
			{
				$this->field_types[$row['field_type']] = $this->include_handler($row['field_type']);
			}

			$this->custom_fields[$row['field_id']] = $row['field_type'];
			
			if ($row['field_type'] == 'date')
			{
				$dfields[$row['site_id']][$row['field_name']] = $row['field_id'];
			}
			elseif ($row['field_type'] == 'rel')
			{
				$rfields[$row['site_id']][$row['field_name']] = $row['field_id'];
			}
			else
			{
				$field_handler = $this->field_types[$row['field_type']];
				$field_handler = is_object($field_handler) ? get_class($field_handler) : $field_handler;
				
				// Yay for PHP 4
				$class_vars = get_class_vars($field_handler);

				if (isset($class_vars['has_array_data']) && $class_vars['has_array_data'] === TRUE)
				{
					$pfields[$row['site_id']][$row['field_id']] = $row['field_type'];
				}
			}
			
			$cfields[$row['site_id']][$row['field_name']] = $row['field_id'];
		}

		return array(
			'custom_channel_fields'	=> $cfields,
			'date_fields'			=> $dfields,
			'relationship_fields'	=> $rfields,
			'pair_custom_fields'	=> $pfields
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Include a custom field handler
	 *
	 * @access	public
	 */
	function include_handler($field_type)
	{
		if ( ! $this->ee_base_ft)
		{
			require_once APPPATH.'fieldtypes/EE_Fieldtype.php';
			$this->ee_base_ft = TRUE;
		}
		
		if ( ! isset($this->field_types[$field_type]))
		{
			$file = 'ft.'.$field_type.EXT;
			$path = PATH_FT;
			
			// @todo hardcode first party array so we only need to check one dir?
			if ( ! file_exists($path.$file))
			{
				$path = PATH_THIRD.$field_type.'/';
				if ( ! file_exists($path.$file))
				{
					return FALSE;
				}
			}
			
			require $path.$file;
						
			$this->ft_paths[$field_type] = $path;
			$this->field_types[$field_type] = ucfirst($field_type.'_ft');
		}
		
		return $this->field_types[$field_type];
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Setup or re-initialize field type handler
	 *
	 * @access	public
	 */
	function setup_handler($field_type)
	{
		$field_id = FALSE;
		$frontend = FALSE;
		
		// Might be a field id
		if (isset($this->custom_fields[$field_type]))
		{
			$frontend = TRUE;
			$field_id = $field_type;
			$field_type = $this->custom_fields[$field_type];
		}
		elseif (isset($this->settings[$field_type]))
		{
			$field_id = $field_type;
			$field_type = $this->settings[$field_id]['field_type'];
		}
		
		// Not found? Bail out.
		if ( ! isset($this->field_types[$field_type]))
		{
			return FALSE;
		}
		
		// Instantiate if we haven't used it yet
		if ( ! is_object($this->field_types[$field_type]))
		{
			$class = $this->field_types[$field_type];
			$this->include_handler($field_type);
			$this->field_types[$field_type] = new $class();
		}
		
		if ($field_id && ! $frontend)
		{
			$settings	= $this->get_settings($field_id);
			$field_name	= $settings['field_name'];
		}
		else
		{
			$settings	= array();
			$field_id	= $field_id;
			$field_name	= FALSE;
		}
		
		// Init settings
		$this->field_types[$field_type]->_init(array(
			'settings'		=> $settings,
			'field_id'		=> $field_id,
			'field_name'	=> $field_name
		));
		
		// Remember the last one
		$this->field_type = $field_type;
		
		return TRUE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Route the call to the proper handler
	 *
	 * Doing it this way so we don't have to pass objects around with PHP 4
	 * being annoying as it is.
	 *
	 * @access	public
	 * @todo cache package paths - sloooow
	 */
	function apply($method, $parameters = array())
	{
		$_old_view_path = $this->EE->load->_ci_view_path;
		$_ft_path = $this->ft_paths[$this->field_type];
		
		$this->EE->load->_ci_view_path = $_ft_path.'views/';
		$this->EE->load->add_package_path($_ft_path);
		
		$res = call_user_func_array(array(&$this->field_types[$this->field_type], $method), $parameters);
		
		$this->EE->load->remove_package_path($_ft_path);
		$this->EE->load->_ci_view_path = $_old_view_path;
		
		return $res;
	}

	
	// --------------------------------------------------------------------
	
	/**
	 * Get custom field info from modules
	 *
	 * @access	public
	 */	
	function get_module_fields($channel_id, $entry_id = '')
	{
		$tab_modules = $this->get_modules();
		
		$set = FALSE;
		
		if ($tab_modules == FALSE)
		{
			return FALSE;
		}
		
		foreach ($tab_modules as $class_name)
		{
			//  Call Module
			$third_party = FALSE;

			if ( ! class_exists($class_name))
			{
				if (file_exists(APPPATH.'modules/'.strtolower($class_name).'/tab.'.strtolower($class_name).EXT))
				{
					@include_once(APPPATH.'modules/'.strtolower($class_name).'/tab.'.strtolower($class_name).EXT);				
				}
				elseif (file_exists(PATH_THIRD.strtolower($class_name).'/tab.'.strtolower($class_name).EXT))
				{
					$third_party = TRUE;
					@include_once(PATH_THIRD.strtolower($class_name).'/tab.'.strtolower($class_name).EXT);
				}
			}

			$new_name = $class_name.'_tab';
		
			$mod_base_path = ($third_party) ? PATH_THIRD : APPPATH.'modules/';
			$this->EE->load->add_package_path($mod_base_path.strtolower($class_name).'/');

			$OBJ = new $new_name();

			if (method_exists($OBJ, 'publish_tabs') === TRUE)
			{
				// fetch the content
				$fields = $OBJ->publish_tabs($channel_id, $entry_id);

				// @confirm: actually, commenting this out, as I see all kinds of problems here.
				//
				// There's basically no way this *won't* be set, but let's check it anyhow.
				// It'll be returned as an array, so we'll read index 0 to find it.
				// When we find it, we'll append the module's classname to it to prevent
				// collission with other modules with similarly named fields. This namespacing
				// gets stripped when the module data is fetched in the Api_channel_entries
				// function _fetch_module_data(). This function is called for insertion and editing
				// of entries.
				// if (isset($fields[0]['field_id']))
				// {
				// 	$fields[0]['field_id'] = $class_name.'__'.$fields[0]['field_id']; // two underscores
				// }

				$set[$class_name] = $fields;
			}

		// restore our package and view paths
		$this->EE->load->remove_package_path($mod_base_path.strtolower($class_name).'/');
		
		}
		
		return $set;
	}
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Get custom field info from modules
	 *
	 * @access	public
	 */	
	function get_module_methods_old($method, $params = array())
	{
		$tab_modules = $this->get_modules();
		
		$set = FALSE;
		
		if ($tab_modules == FALSE)
		{
			return FALSE;
		}
		
		foreach ($tab_modules as $class_name)
		{
			//  Call Module
			$third_party = FALSE;

			if ( ! class_exists($class_name))
			{
				if (file_exists(APPPATH.'modules/'.strtolower($class_name).'/tab.'.strtolower($class_name).EXT))
				{
					@include_once(APPPATH.'modules/'.strtolower($class_name).'/tab.'.strtolower($class_name).EXT);				
				}
				elseif (file_exists(PATH_THIRD.strtolower($class_name).'/tab.'.strtolower($class_name).EXT))
				{
					$third_party = TRUE;
					@include_once(PATH_THIRD.strtolower($class_name).'/tab.'.strtolower($class_name).EXT);
				}
			}

			$new_name = $class_name.'_tab';
		
			$mod_base_path = ($third_party) ? PATH_THIRD : APPPATH.'modules/';
			$this->EE->load->add_package_path($mod_base_path.strtolower($class_name).'/');

			$OBJ = new $new_name();

			if (method_exists($OBJ, $method) === TRUE)
			{
			// we're going to wipe the view vars here in a sec
			//$file = $vars['file'];
			
				$mod_base_path = ($third_party) ? PATH_THIRD : APPPATH.'modules/';
			
				// add the view paths
				$orig_view_path = $this->EE->load->_ci_view_path;
				$this->EE->load->_ci_view_path = $mod_base_path.strtolower($class_name).'/views/';

				// fetch the content
				$set[$class_name] = $OBJ->$method($params);
			
				// restore our package and view paths
				$this->EE->load->_ci_view_path = $orig_view_path;

			}
		
		// restore our package and view paths
		$this->EE->load->remove_package_path($mod_base_path.strtolower($class_name).'/');
		
		}
		
		return $set;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Get custom field info from modules
	 *
	 * @access	public
	 */	
	function get_module_methods($methods, $params = array())
	{
		$tab_modules = $this->get_modules();
		
		$set = FALSE;
		
		if ($tab_modules == FALSE)
		{
			return FALSE;
		}
		
		if ( ! is_array($methods))
		{
			$methods = array($methods);
		}
		
		foreach ($tab_modules as $class_name)
		{
			//  Call Module
			$third_party = FALSE;

			if ( ! class_exists($class_name))
			{
				if (file_exists(APPPATH.'modules/'.strtolower($class_name).'/tab.'.strtolower($class_name).EXT))
				{
					@include_once(APPPATH.'modules/'.strtolower($class_name).'/tab.'.strtolower($class_name).EXT);				
				}
				elseif (file_exists(PATH_THIRD.strtolower($class_name).'/tab.'.strtolower($class_name).EXT))
				{
					$third_party = TRUE;
					@include_once(PATH_THIRD.strtolower($class_name).'/tab.'.strtolower($class_name).EXT);
				}
			}

			$new_name = $class_name.'_tab';
		
			$mod_base_path = ($third_party) ? PATH_THIRD : APPPATH.'modules/';
			$this->EE->load->add_package_path($mod_base_path.strtolower($class_name).'/');

			$OBJ = new $new_name();

			foreach ($methods as $method)
			{
				if (method_exists($OBJ, $method) === TRUE)
				{
					if ( ! isset($params[$method]))
					{
						$params[$method] = '';
					}
				
					
					// we're going to wipe the view vars here in a sec
					//$file = $vars['file'];
			
					$mod_base_path = ($third_party) ? PATH_THIRD : APPPATH.'modules/';
			
					// add the view paths
					$orig_view_path = $this->EE->load->_ci_view_path;
					$this->EE->load->_ci_view_path = $mod_base_path.strtolower($class_name).'/views/';

					// fetch the content
					$set[$class_name][$method] = $OBJ->$method($params[$method]);
			
					// restore our package and view paths
					$this->EE->load->_ci_view_path = $orig_view_path;

				}
			}
		
		// restore our package and view paths
		$this->EE->load->remove_package_path($mod_base_path.strtolower($class_name).'/');
		
		}

		return $set;
	}


	function get_modules()
	{
		// Do we have modules in play
		$this->EE->load->model('addons_model');
		$custom_field_modules = FALSE;
		
		$mquery = $this->EE->addons_model->get_installed_modules(FALSE, TRUE);
			
		if ($mquery->num_rows > 0)
		{
			foreach($mquery->result_array() as $row)
			{
				$custom_field_modules[] = $row['module_name'];
			}
		}
		
		return $custom_field_modules;
	}
}

// END Api_channel_fields class

/* End of file Api_channel_fields.php */
/* Location: ./system/expressionengine/libraries/api/Api_channel_fields.php */