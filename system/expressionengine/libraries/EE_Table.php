<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Use the table library as usual, but with added steps to setup the datatable:
 *
 * 1. Name your columns and set their sorting properties:
 *
 * $this->table->set_columns(array('id' => FALSE, 'name' => TRUE));
 *
 * @todo sorting datatype? (bit of a pain to let them pick, must work in js and mysql, maybe just ask them what sql will sort on)
 * @todo if you just need non-ajax sorting, should be done here.
 * @todo allow Wes's tokens? 	'entry_id' => array('filter' => TRUE, 'token' => TRUE, 'alias' => 'id')
 * 
 * 
 * 2. Define a function in your controller that will act as the datasource.
 *
 * The function should return an array containing the following data:
 *
 * rows - an array of table rows. Each row is an array similar
 * to the normal add_row() parameter, but with a key equal to the column name.
 *
 * no_results - html to display if filtering results in no results
 *
 * Pagination settings:
 *
 * total_rows - number of rows without php
 * per_page - number of rows per page
 * *_link - link styles as per pagination
 * full_tag_* - as per pagination
 *
 * Using names from the previous example:
 * return array(
 *	   array('id' => 5, 'name' => 'pascal'),
 *	   array('id' => 3, 'name' => 'wes')
 * );
 *
 * Hint: This matches the db->result_array() output [db->result() will work as well].
 *
 * 
 * 3. Connect the datasource to your table:
 * $table_data = $this->table->datasource('somefunc');
 *
 * Your datasource will receive an parameter that contains the current row offset
 * and sorting requirements in this format [columns specified to be non-
 * sortable will be removed from the sort array.]:
 * 
 * array(
 *     'offset' => 2,
 *     'sort' 	=> array('name' => 'asc/desc'),
 *	   'columns' => array('id' => FALSE, 'name' => TRUE)
 * );
 *
 *
 * If this is a filtering/pagination/sorting call, the request will stop here
 * the table will automatically be updated using the data returned from the
 * datasource. As a result, you should try to call this function as soon as
 * possible to avoid filtering delays.
 *
 * On a regular (non-ajax) load this call will return a slightly modified version
 * of your datasource return array:
 * 
 * 1. The 'row' and 'pagination' keys will be removed.
 * 2. Two keys will be added: table_html and pagination_html
 *
 * table_html is generated using your existing table configuration, make sure your
 * table headers and template are set before returning from your datasource.
 *
 * If you did not provide pagination configuration, in your return pagination_html
 * will be blank.
 *
 * Note: If you need to provide additional data to your datasource, you can do so by adding
 * an array as a second parameter to table->datasource(). This will be passed to your
 * datasource method as the second parameter. Additionally, it can be used to set defaults
 * for the page (reg. default = 1) and sorting configuration, by prefixing them with tbl_
 *
 * array(
 *     'tbl_offset' => 4,
 *	   'tbl_sort' => array('entry_date' => 'desc')
 * )
 *
 *
 * 4. Include the javascript (tmpl and table plugins):
 * 
 * $this->cp->add_js_script('plugin' => array('tmpl', 'table'))
 *
 *
 * 5. Tying into the javascript:
 *
 * The table setup is done automatically by grabbing all tables and
 * looking for the data-table_config property. That property contains a json
 * array that is passed to the plugin.
 *
 * The table plugin will modify the table as users interact with it. If your javascript
 * modifies the table or listens for events on its elements, you will need to observe
 * table updates to ensure that your code continues to function: (@todo crappy paragraph, pascal)
 * 
 * WIP list:
 *
 * $('table').bind('tablecreate')	// initial automatic setup
 * $('table').bind('tableload')		// beginning of (potentially) long process: show indicator
 * $('table').bind('tableupdate')	// results returned and changes applied (pagination/sorting/filtering)
 *
 * If you want to filter, you will need to connect the filtering plugin. You can
 * either give it a serializable set of form elements (form, or multiple inputs).
 * These will automatically be observed for changes.
 *
 * $('table').table('add_filter', $('form'));
 *
 * Or you can apply filters yourself, by passing a json object:
 *
 * $('table').table('add_filter', {'foo': 'bar'});
 *
 * multiple calls to add_filter stack
 *
 * You can also remove filters:
 * $('table').table('remove_filter', 'key'/object/serializable);
 *
 * Or clear them:
 * $('table').table('clear_filter');
 *
 */

/**
 * Notes:
 *
 * Protected column names: data
 *
 * @todo need to support array/data syntax for table cells (on js end):
 *
 */

class EE_Table extends CI_Table {

	protected $EE;
	protected $uniqid = '';
	protected $no_results = '';
	protected $pagination_tmpl = '';
	protected $jq_template = FALSE;
	protected $column_config = array();

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function __construct($config = array())
	{
		parent::__construct($config);
		
		$this->EE =& get_instance();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Setup the datasource
	 *
	 * @access	public
	 * @param	string	data callback function
	 * @param	mixed	default data that will later be passed in the get array
	 */
	function datasource($func, $options = array())
	{
		$settings = array(
			'offset'		=> 0,
			'sort'			=> array(),		// column_name => value
			'columns'		=> $this->column_config
		);
		
		// override settings on non-ajax load
		foreach (array_keys($settings) as $key)
		{
			if (isset($options['tbl_'.$key]))
			{
				$settings[$key] = $options['tbl_'.$key];
				unset($options['tbl_'.$key]);
			}
		}
		
		// override settings from GET (must be get for pagination to work)
		if (is_numeric($this->EE->input->get('tbl_offset')))
		{
			$settings['offset'] = $_GET['tbl_offset'];
		}

		if (isset($_GET['tbl_sorting']))
		{
			$settings['sorting'] = $_GET['tbl_sorting'];
		}
		
		
		// datasource returns PHP array (shown in js syntax for brevity):
		/*
		{
			no_results: 'something',
			total_rows: 44038,
			rows: [
				{key: value, key2: value2, key3: value3},
				{key: eulav, key2: eulav2, key3: eulav3},
			],
			pagination: [
				base_url: 
			]
		*/
		$data = $this->EE->$func($settings, $options);
		
		$this->uniqid = uniqid('tbl_');
		$this->no_results = $data['no_results'];
		
		if (AJAX_REQUEST)
		{
			// do we need to apply a cell function?
			if ($this->function)
			{
				// @todo loop through rows array_map cells?
			}
			
			$this->EE->output->send_ajax_response(array(
				'rows'		 => $data['rows'],
				'total_rows' => $data['total_rows'],
				'pagination' => $this->_create_pagination($data, TRUE)
			));
		}
		
		// make sure we add a jq template
		$this->jq_template = TRUE;
		
		// remove the key information from the row data to make it usable
		// by the CI generate function. Unfortunately that means reordering
		// to match our columns. Easy enough, simply overwrite the booleans.
		foreach ($data['rows'] as &$row)
		{
			$row = array_values(array_merge($this->column_config, $row));
		}
		
		$data['pagination_html'] = $this->_create_pagination($data);
		$data['table_html'] = $this->generate($data['rows']);
		
		return $data;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Setup columns
	 *
	 * @access	public
	 * @param	mixed	column key => sort[bool]
	 */
	public function set_columns($cols = array())
	{
		// @todo hook to register column?
		$this->column_config = $cols;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Setup columns
	 *
	 * @access	public
	 * @param	string	data callback function
	 */
	public function generate($table_data = NULL)
	{
		$this->_compile_template();
		
		if ( ! $this->jq_template)
		{
			return parent::generate($table_data);
		}
		
		$open_bak = $this->template['table_open'];
		$templates = array(
			'template' => '',
			'template_alt' => 'alt_'
		);
		
		// two templates for alternating rows
		// this may prove to be annoying with dynamic filtering
		foreach ($templates as $var => $k)
		{
			$temp = $this->template['row_'.$k.'start'];

			foreach($this->column_config as $column => $config)
			{
				$html = FALSE;
				
				if (is_array($config))
				{
					$html = (isset($config['html'])) ? (bool) $config['html'] : FALSE;
				}
				
				$temp .= $this->template['cell_'.$k.'start'];
				$temp .= $html ? '{{html '.$column.'}}' : '${'.$column.'}';
				$temp .= $this->template['cell_'.$k.'end'];
			}

			$temp .= $this->template['row_'.$k.'end'];
			$$var = $temp;
		}
		
		
		$jq_config = array(
			'columns'		=> $this->column_config,
			'template'		=> $template,
			'template_alt'	=> $template_alt,
			'empty_cells'	=> $this->empty_cells,
			'no_results'	=> $this->no_results,
			'pagination'	=> $this->pagination_tmpl,
			'uniqid'		=> $this->uniqid
		);
		
		$table_config_data = 'data-table_config="'.form_prep($this->EE->javascript->generate_json($jq_config, TRUE)).'"';
		$this->template['table_open'] = str_replace(
			'<table',
			'<table '.$table_config_data,
			$open_bak
		);
		
		$table = parent::generate($table_data);
		
		$this->template['table_open'] = $open_bak;
		return $table;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Setup async table refreshing
	 *
	 * @access	public
	 * @param	string	data callback function
	 */
	public function clear()
	{
		$this->uniqid = '';
		$this->no_result = '';
		$this->pagination_tmpl = '';
		$this->column_config = array();
		$this->jq_template = FALSE;
		
		parent::clear();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Setup table pagination
	 *
	 * @access	protected
	 * @param	string	pagination html
	 */
	protected function _create_pagination($data, $ajax_request = FALSE)
	{
		if ( ! isset($data['pagination']))
		{
			return '';
		}
		
		if ( ! isset($data['pagination']['base_url']))
		{
			return '';
		}
		
		// sensible CP defaults
		$config = array(
			'per_page'				=> 50,
			'total_rows'			=> $data['total_rows'],
			
			'page_query_string'		=> TRUE,
			'query_string_segment'	=> 'tbl_offset',
			
			'full_tag_open'			=> '<p id="paginationLinks">', // having an id here is nonsense, you can have more than one!
			'full_tag_close'		=> '</p>',
			
			'prev_link'				=> '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="&lt;" />',
			'next_link'				=> '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt="&gt;" />',
			'first_link'			=> '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="&lt; &lt;" />',
			'last_link'				=> '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="&gt; &gt;" />'
		);
		
		$config = array_merge($config, $data['pagination']);
		
		// add the uniqid as a class so we can find it from
		// the table. Note: You can have multiple instances
		// of the pagination html on the page.
		if (strpos($config['full_tag_open'], 'class')) // will never be 0
		{
			$config['full_tag_open'] = preg_replace(
				'#class\s*=\s*(\042|\047)#i',
				'$0'.$this->uniqid.' ',
				$config['full_tag_open']
			);
		}
		else
		{
			$config['full_tag_open'] = preg_replace(
				'#(<\w+)#i',
				'$1 class="'.$this->uniqid.'"',
				$config['full_tag_open']
			);
		}
		
		$this->EE->load->library('pagination');
		$this->EE->pagination->initialize($config);
		
		if ($ajax_request)
		{
			return $this->EE->pagination->create_link_array();
		}
		
		$p = $this->EE->pagination;
		
		
		$temp = $p->full_tag_open;
		
		$temp .= '{{if first_page && first_page[0]}}';
		$temp .= $p->first_tag_open.'<a '.$p->anchor_class.'href="${first_page[0].pagination_url}">{{html first_page[0].text}}</a>'.$p->first_tag_close;
		$temp .= '{{/if}}';
	
		$temp .= '{{if previous_page && previous_page[0]}}';
		$temp .= $p->prev_tag_open.'<a '.$p->anchor_class.'href="${previous_page[0].pagination_url}">{{html previous_page[0].text}}</a>'.$p->prev_tag_close;
		$temp .= '{{/if}}';
	
	
		$temp .= '{{each(i, c_page) page}}';
			$temp .= '{{if c_page.current_page}}';
			$temp .= $p->cur_tag_open.'${c_page.pagination_page_number}'.$p->cur_tag_close;
			$temp .= '{{else}}';
			$temp .= $p->num_tag_open.'<a '.$p->anchor_class.'href="${c_page.pagination_url}">${c_page.pagination_page_number}</a>'.$p->num_tag_close;
			$temp .= '{{/if}}';
		$temp .= '{{/each}}';
	
	
		$temp .= '{{if next_page && next_page[0]}}';
		$temp .= $p->next_tag_open.'<a '.$p->anchor_class.'href="${next_page[0].pagination_url}">{{html next_page[0].text}}</a>'.$p->next_tag_close;
		$temp .= '{{/if}}';
	
		$temp .= '{{if last_page && last_page[0]}}';
		$temp .= $p->last_tag_open.'<a '.$p->anchor_class.'href="${last_page[0].pagination_url}">{{html last_page[0].text}}</a>'.$p->last_tag_close;
		$temp .= '{{/if}}';
		
		$temp .= $p->full_tag_close;
		
		$this->pagination_tmpl = $temp;
		unset($temp);
		
		return $this->EE->pagination->create_links();
	}
}

// END EE_Table class


/* End of file EE_Table.php */
/* Location: ./system/expressionengine/libraries/EE_Table.php */