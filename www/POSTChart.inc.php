<?php

abstract class POSTChart
{
	protected $_id;
	protected $_drawing;
	protected $_type;

	// 2D array [row][col]
	protected $_content;

	protected $_cols;

	// Should we draw from an import array?
	protected $_drawFromArray = FALSE;

	// factory method to create an object of the correct type
	public static function create($id)
	{
		global $DB;

		$drawing = $DB->SingleQuery('SELECT main.*, schools.school_abbr, schools.school_name, d.num_rows, d.num_extra_rows, `d`.`footer_text`, `d`.`footer_link`, `d`.`id`
			FROM post_drawing_main AS main, post_drawings AS d, schools
			WHERE d.parent_id = main.id
				AND main.school_id = schools.id
				AND d.id = '.$id.'
				AND deleted = 0');
		if( is_array($drawing) ) 
		{
			switch( $drawing['type'] )
			{
				case 'HS':
					return new POSTChart_HS($drawing);
				case 'CC':
					return new POSTChart_CC($drawing);
				default:
					throw new Exception('No drawing type was found in the record.');
			}
		}	
	}

	public static function createFromArray($chartType, &$array)
	{
		//sanity checking
		if($chartType != 'HS' && $chartType != 'CC')
			throw new Exception('Invalid Chart Type Initialized.');

		// Create the appropriate POSTChart type
		if($chartType == 'HS')
		{
			$post = new POSTChart_HS(array('id'=>'temp1234'), $array);
		}
		else
		{
			$post = new POSTChart_CC(array('id'=>'temp1234'), $array);
		}

		return $post;
	}

	public function __construct($drawing, $array = null)
	{
		global $DB;

		$this->_id = $drawing['id'];

		if(is_array($array))
		{
			$this->_drawFromArray = TRUE;
			$this->_drawing = $array['drawing'];
			$this->_content = $array['content'];
			$this->_cols = $array['headers'];
			$this->_loadDataFromArray();
		}
		else
		{
			$this->_drawing = $drawing;
		}
	}

	// create an empty $_cells array of the appropriate size
	private function _initCells()
	{
		global $DB;

		$num_rows = $this->_drawing['num_rows'];
		$num_extra_rows = $this->_drawing['num_extra_rows'];

		$cols = $DB->MultiQuery('SELECT * FROM post_col WHERE drawing_id=' . $this->_id . ' ORDER BY num');

		// store the column names for later
		foreach( $cols as $col )
		{
			$this->_cols[$col['num']] = new POSTCol($col);
		}

		// create the empty 2D array
		for( $row = 1; $row <= $num_rows; $row++ )
		{
			$this->_content[$row] = array();
			foreach( $cols as $col )
			{
				$this->_content[$row][$col['num']] = new POSTCell();
			}
		}
		for( $row = 100; $row <= $num_extra_rows+99; $row++ )
		{
			$this->_content[$row] = array();
			foreach( $cols as $col )
			{
				$this->_content[$row][$col['num']] = new POSTCell();
			}
		}
	}


	// populate the $_content array from the DB
	private function _loadData()
	{
		global $DB;

		// load cell data
		$cells = $DB->MultiQuery('
			SELECT cell.*, col.num AS col_num
			FROM post_cell AS cell
			JOIN post_col AS col ON cell.col_id=col.id
			WHERE cell.drawing_id = ' . $this->_id . '
		');
		foreach( $cells as $cell )
		{
			$this->_content[$cell['row_num']][$cell['col_num']] = new POSTCell($cell);
		}
	}

	// populate the $_content from an array
	private function _loadDataFromArray()
	{
		$tempArray = array();

		foreach($this->_content as $row)
			foreach($row as $cell)
				$tempArray[$cell['row_num']][$cell['col_num']] = new POSTCell($cell);

		$this->_content = $tempArray;
		$tempArray = array();

		foreach($this->_cols as $col)
			$tempArray[] = new POSTCol($col);
		$this->_cols = $tempArray;
	}

	public function display()
	{
		// Groom our database information if we're not previewing an import
		if(!$this->_drawFromArray)
		{
			$this->_initCells();
			$this->_loadData();
		}

		echo '<table border="1" class="post_chart">', "\n";
		$this->_printHeaderRow();

		foreach( $this->_content as $rowNum=>$row )
		{
			echo '<tr>', "\n";
			echo '<td class="post_head_row post_head">' . $this->_rowName($rowNum) . '</td>', "\n";
			foreach( $row as $cell )
			{
				echo '<td id="post_cell_' . $cell->id . '" class="post_cell">' . $this->_cellContent($cell) . '</td>', "\n";
			}
			echo '</tr>', "\n";
		}
		echo '<tr>', "\n";

		echo '<td id="post_footer_' . $this->_id . '" class="post_footer" colspan="' . $this->footerCols . '">'
			. ($this->_drawing['footer_link']?'<a href="javascript:void(0);">':'')
			. $this->_drawing['footer_text']
			. ($this->_drawing['footer_link']?'</a>':'')
			. '</td>', "\n";
		echo '</tr>', "\n";
		echo '</table>', "\n";
	}

	// For copying drawings, to give the copy a different name
	public function setDrawingName($name)
	{
		$this->_drawing['name'] = $name;
	}

	// For copying drawings, to change the school
	public function setSchoolID($id)
	{
		global $DB;
		$this->_drawing['school_id'] = $id;
		$school = $DB->SingleQuery('SELECT * FROM schools WHERE id='.intval($id));
		$this->_drawing['school_name'] = $school['school_name'];
		$this->_drawing['school_abbr'] = $school['school_abbr'];
	}

	public function saveToDB()
	{
		global $DB;

		// create post_drawing_main record
		$post_drawing_main = array();
		$post_drawing_main['school_id'] = $this->_drawing['school_id'];
		$post_drawing_main['name'] = $this->_drawing['name'];
		$post_drawing_main['code'] = CreateDrawingCodeFromTitle($this->_drawing['name'], $this->_drawing['school_id'], 0, 'post');
		$post_drawing_main['date_created'] = $DB->SQLDate();
		$post_drawing_main['last_modified'] = $DB->SQLDate();
		$post_drawing_main['created_by'] = $_SESSION['user_id'];
		$post_drawing_main['last_modified_by'] = $_SESSION['user_id'];
		$post_drawing_main['type'] = $this->_type;
		$post_drawing_main_id = $DB->Insert('post_drawing_main', $post_drawing_main);

		$post_drawing = array();
		$post_drawing['parent_id'] = $post_drawing_main_id;
		$post_drawing['version_num'] = 1;
		$post_drawing['footer_text'] = $this->_drawing['footer_text'];
		$post_drawing['footer_link'] = $this->_drawing['footer_link'];
		$post_drawing['published'] = 0;
		$post_drawing['frozen'] = 0;
		$post_drawing['deleted'] = 0;
		$post_drawing['date_created'] = $DB->SQLDate();
		$post_drawing['last_modified'] = $DB->SQLDate();
		$post_drawing['created_by'] = $_SESSION['user_id'];
		$post_drawing['last_modified_by'] = $_SESSION['user_id'];

		$post_drawing['num_rows'] = $this->_drawing['num_rows'];
		$post_drawing['num_extra_rows'] = $this->_drawing['num_extra_rows'];

		$post_drawing_id = $DB->Insert('post_drawings', $post_drawing);

		$colmap = array();
		foreach( $this->_cols as $i=>$col )
		{
			$post_col = array();
			$post_col['drawing_id'] = $post_drawing_id;
			$post_col['title'] = dv($col->title);
			$post_col['num'] = $i+1;
			$post_col_id = $DB->Insert('post_col', $post_col);
			$colmap[$i+1] = $post_col_id;
		}

		foreach( $this->_content as $row_num=>$row )
		{
			foreach( $row as $cell )
			{
				$post_cell = array();
				$post_cell['drawing_id'] = $post_drawing_id;
				$post_cell['row_num'] = $row_num;
				$post_cell['col_id'] = (array_key_exists($cell->col_num, $colmap) ? $colmap[$cell->col_num] : -1);
				$post_cell['content'] = dv($cell->content);
				$post_cell['course_subject'] = dv($cell->course_subject);
				$post_cell['course_number'] = dv($cell->course_number);
				$post_cell['course_title'] = dv($cell->course_title);
				$DB->Insert('post_cell', $post_cell);
			}
		}

		return $post_drawing_id;
	}

	protected abstract function _printHeaderRow();
	protected abstract function _rowName($num);
	
	public function verticalText($text)
	{
		return '<img src="/files/postv/' . base64_encode($text) . '.png" alt="' . $text . '" />';	
	}
	
	public function __get($key)
	{
		// some predefined variables
		switch( $key )
		{
			case 'totalRows':
				return count($this->_content) + 2;
				
			case 'totalCols':
				return count($this->_cols) + 3;
				
			case 'footerCols':
				return $this->totalCols - 2;
			
			case 'schoolName':
				return $this->_drawing['school_name'];
				
			case 'drawingName':
				return $this->_drawing['name'];
		}

		// lastly, check for any keys in the drawing record
		if( array_key_exists($key, $this->_drawing) )
			return $this->_drawing[$key];
		else
			return null;		
	}
	
}


class POSTChart_HS extends POSTChart
{
	protected $_type = "HS";

	protected function _rowName($num)
	{
		switch( $num )
		{
			case 1:
			case 2:
			case 3:
			case 4:
				return '' . $num+8;
			default:
				return '';
		}
	}

	protected function _printHeaderRow()
	{
		echo '<tr>', "\n";
			echo '<td class="post_sidebar_left" rowspan="' . $this->totalRows . '">' . $this->verticalText($this->schoolName). '</td>', "\n";
			echo '<th class="post_head_xy post_head">Grade</th>', "\n";
			foreach( $this->_cols as $col )
			{
				echo '<th id="post_header_' . $col->id . '" class="post_head_main post_head">' . $col->title . '</th>', "\n";
			}
			echo '<td  class="post_sidebar_right" rowspan="' . $this->totalRows . '">' . $this->verticalText('High School Diploma') . '</td>', "\n";
		echo '</tr>', "\n";
	}

	protected function _cellContent(&$cell)
	{
		// Is there a link?
		$link = ($cell->href != '');

		// Draw the item inside the post_cell
		return ($link?'<a href="' . $cell->href . '">':'') . (($cell->content)?htmlentities($cell->content):'') . ($link?'</a>':'');
	}
}

class POSTChart_CC extends POSTChart
{
	protected $_type = "CC";

	protected function _rowName($num)
	{
		return ($num < 100 ? ucfirst(ordinalize($num)) . ' Term' : '');
	}

	protected function _printHeaderRow()
	{
		echo '<tr>', "\n";
			echo '<td class="post_sidebar_left" valign="middle" rowspan="' . $this->totalRows . '">' . $this->verticalText($this->schoolName). '</td>', "\n";
			echo '<th class="post_head_xy post_head" style="width:40px;">Term</th>', "\n";
			echo '<th class="post_head_main post_head post_head_noClick" colspan="' . count($this->_cols) . '">' . $this->drawingName . '</th>', "\n";
			echo '<td class="post_sidebar_right" valign="middle" rowspan="' . $this->totalRows . '">' . $this->verticalText('Career Pathway Certificate of Completion') . '</td>', "\n";
		echo '</tr>', "\n";
	}

	protected function _cellContent(&$cell)
	{
		if( $cell->course_subject )
		{
			return '<a href="#">' . $cell->course_subject . ' ' . $cell->course_number . '<br />' . $cell->course_title . '</a>';
		}
		else
		{
			// Is there a link?
			$link = ($cell->href != '');
	
			// Draw the item inside the post_cell
			return ($link?'<a href="' . $cell->href . '">':'') . (($cell->content)?htmlentities($cell->content):'') . ($link?'</a>':'');

		}
	}
}


class POSTCell
{
	private $_data;
	
	public function __construct($data=array())
	{
		$this->_data = $data;
	}

	public function __get($key)
	{
		if( is_array($this->_data) && array_key_exists($key, $this->_data) )
			return $this->_data[$key];
		else
			return NULL;
	}
}

class POSTCol
{
	private $_data;
	
	public function __construct($data=array())
	{
		$this->_data = $data;
	}

	public function __get($key)
	{
		if( is_array($this->_data) && array_key_exists($key, $this->_data) )
			return $this->_data[$key];
		else
			return NULL;
	}
}

?>