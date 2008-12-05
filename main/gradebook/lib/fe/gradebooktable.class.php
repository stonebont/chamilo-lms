<?php
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2008 Dokeos SPRL

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact address: Dokeos, rue du Corbeau, 108, B-1030 Brussels, Belgium
	Mail: info@dokeos.com
==============================================================================
*/


require_once (dirname(__FILE__).'/../../../inc/global.inc.php');
require_once (dirname(__FILE__).'/../be.inc.php');

/**
 * Table to display categories, evaluations and links
 * @author Stijn Konings
 * @author Bert Steppé (refactored, optimised)
 */
class GradebookTable extends SortableTable
{

	private $currentcat;
	private $datagen;
	private $evals_links;

	/**
	 * Constructor
	 */
    function GradebookTable ($currentcat, $cats = array(), $evals = array(), $links = array(), $addparams = null) {
    	parent :: SortableTable ('gradebooklist', null, null, (api_is_allowed_to_create_course()?1:0));
		$this->evals_links = array_merge($evals, $links);
		$this->currentcat = $currentcat;

		$this->datagen = new GradebookDataGenerator($cats, $evals, $links);
		if (isset($addparams)) {
			$this->set_additional_parameters($addparams);
		}
		$column= 0;
		if (api_is_allowed_to_create_course() && ($_SESSION['studentview']<>'studentview') || (isset($_GET['isStudentView']) && $_GET['isStudentView']=='false')) {
			$this->set_header($column++, '', false);
		}	
		$this->set_header($column++, get_lang('Type'));
		$this->set_header($column++, get_lang('Name'));
		$this->set_header($column++, get_lang('Description'));
		if (api_is_allowed_to_create_course() && $_SESSION['studentview']<>'studentview' || (isset($_GET['isStudentView']) && $_GET['isStudentView']=='false')) {
			$this->set_header($column++, get_lang('Weight'));	
		} else {
			if (!isset($_GET['selectcat']) || ($_SESSION['studentview']=='studentview' || (isset($_GET['isStudentView']) && $_GET['isStudentView']=='true')) || !api_is_allowed_to_create_course()) {	
				$this->set_header($column++, get_lang('Evaluation'));
			}
			else {
			$this->set_header($column++, get_lang('Weight'));	
			
			}	
		}		 
							
		$this->set_header($column++, get_lang('Date'),true, 'width="100"');				
		
		//admins get an edit column
		if (api_is_allowed_to_create_course() && $_SESSION['studentview']<>'studentview' || (isset($_GET['isStudentView']) && $_GET['isStudentView']=='false')) {
			$this->set_header($column++, get_lang('Modify'), false, 'width="100"');
			//actions on multiple selected documents
			$this->set_form_actions(array (
				'deleted' => get_lang('DeleteSelected'),
				'setvisible' => get_lang('SetVisible'),
				'setinvisible' => get_lang('SetInvisible')));
		} else {
	            $evals_links = array_merge($evals, $links);
 	             if(count($evals_links)>0) {
 	             	$this->set_header($column++, get_lang('Results'), false);
 	             }
		 	    if (!isset($_GET['selectcat']) ||(isset($_GET['isStudentView']) && $_GET['isStudentView']=='true') || !api_is_allowed_to_create_course()) {         
					$this->set_header($column++, get_lang('Certificates'),false);
		 	    }	
		}
    }


	/**
	 * Function used by SortableTable to get total number of items in the table
	 */
	function get_total_number_of_items() {
		return $this->datagen->get_total_items_count();
	}


	/** 
	 * Function used by SortableTable to generate the data to display
	 */
	function get_table_data($from = 1) {

		// determine sorting type
		$col_adjust = (api_is_allowed_to_create_course() ? 1 : 0);
		switch ($this->column) {
			// Type
			case (0 + $col_adjust) :
				$sorting = GradebookDataGenerator :: GDG_SORT_TYPE;
				break;
			case (1 + $col_adjust) :
				$sorting = GradebookDataGenerator :: GDG_SORT_NAME;
				break;
			case (2 + $col_adjust) :
				$sorting = GradebookDataGenerator :: GDG_SORT_DESCRIPTION;
				break;
			case (3 + $col_adjust) :
				$sorting = GradebookDataGenerator :: GDG_SORT_WEIGHT;
				break;
			case (4 + $col_adjust) :
				$sorting = GradebookDataGenerator :: GDG_SORT_DATE;
				break;
		}
		if ($this->direction == 'DESC') {
			$sorting |= GradebookDataGenerator :: GDG_SORT_DESC;
		} else {
			$sorting |= GradebookDataGenerator :: GDG_SORT_ASC;
		}
		$data_array = $this->datagen->get_data($sorting, $from, $this->per_page);
		// generate the data to display
		$sortable_data = array();
		foreach ($data_array as $data) {
			$row = array ();

			$item = $data[0];
			if (!isset($_GET['selectcat'])) {
				$certificate_min_score = $this->build_cetificate_min_score($item);
			}														
			//if the item is invisible, wrap it in a span with class invisible
			$invisibility_span_open = (api_is_allowed_to_create_course() && $item->is_visible() == '0') ? '<span class="invisible">' : '';
			$invisibility_span_close = (api_is_allowed_to_create_course() && $item->is_visible() == '0') ? '</span>' : '';
			
			
			if (api_is_allowed_to_create_course() && ($_SESSION['studentview']<>'studentview') || (isset($_GET['isStudentView']) && $_GET['isStudentView']=='false')) {
				$row[] = $this->build_id_column ($item);
			}
			$row[] = $this->build_type_column ($item);
			$row[] = $invisibility_span_open . $this->build_name_link ($item) . $invisibility_span_close;
			$row[] = $invisibility_span_open . $data[2] . $invisibility_span_close;
			
			if (api_is_allowed_to_create_course()) {
			$row[] = $invisibility_span_open . $data[3] . $invisibility_span_close;	
			} else {
				
				if (!isset($_GET['selectcat']) && isset($certificate_min_score)) {					
					// generating the total score for a course
				    $stud_id= api_get_user_id();
				      
					$cats_course = Category :: load (0, null, null, null, null, null, false);
					$alleval_course= $cats_course[0]->get_evaluations($stud_id,true);
					$alllink_course= $cats_course[0]->get_links($stud_id,true);
					$evals_links = array_merge($alleval_course, $alllink_course);	
					$item_value=0;
					$item_total=0;				
					for ($count=0; $count < count($evals_links); $count++) {
								$item = $evals_links[$count];
								$score = $item->calc_score($stud_id);					
								$item_value+=$score[0]/$score[1]*$item->get_weight();			
								$item_total+=$item->get_weight();															
							}			
					$item_value = number_format($item_value, 2, '.', ' ');	
					$cattotal = Category :: load(0);
					$scoretotal= $cattotal[0]->calc_score(api_get_user_id());
					$scoretotal_display = (isset($scoretotal) ? $scoretotal[0].'/'.$scoretotal[1].'('.round(($scoretotal[0] / $scoretotal[1]) * 100) . ' %)': get_lang('NoResultsAvailable'));
					$row[] = $item_value;
				} else {
			   		$row[] = $invisibility_span_open . $data[3] . $invisibility_span_close;	
			   }											
			}	
			$row[] = $invisibility_span_open . str_replace(' ','&nbsp;',$data[4]) . $invisibility_span_close;
			
			//admins get an edit column
			if (api_is_allowed_to_create_course() && ($_SESSION['studentview']<>'studentview' || (isset($_GET['isStudentView']) && $_GET['isStudentView']=='false'))) {
				$row[] = $this->build_edit_column ($item);
			} else {
			//students get the results and certificates columns
				if (count($this->evals_links)>0) {
					$row[] = $data[5];
				}
				if (!isset($_GET['selectcat']) && isset($certificate_min_score) && !api_is_allowed_to_create_course) {
					if ((int)$item_value >= (int)$certificate_min_score) {
						$certificates = '<a href="'.api_get_path(WEB_CODE_PATH) .'gradebook/index.php?export_certificate=yes"><img src="'.api_get_path(WEB_CODE_PATH) . 'img/dokeos.gif" /></a>&nbsp;'.$scoretotal_display; 	
					} else {
						$certificates = get_lang('NoResultsAvailable');	
					}					
				$row[] = $certificates;
				}												
			}
			$sortable_data[] = $row;
		}
		
		return $sortable_data;
	}
// Other functions


private function build_cetificate_min_score ($item) {
	return $item->get_certificate_min_score();
}

private function build_id_column ($item) {
		switch ($item->get_item_type()) {
			// category
			case 'C' :
				return 'CATE' . $item->get_id();
			// evaluation
			case 'E' :
				return 'EVAL' . $item->get_id();
			// link
			case 'L' :
				return 'LINK' . $item->get_id();				
		}
	}

	private function build_type_column ($item) {
		return build_type_icon_tag($item->get_icon_name());
	}

	private function build_name_link ($item) {
		
		switch ($item->get_item_type()) {
			// category
			case 'C' :
				$prms_uri='?selectcat=' . $item->get_id();
				if ( isset($_GET['isStudentView']) || ( isset($_SESSION['studentview']) && $_SESSION['studentview']=='studentview') ) {
					$prms_uri=$prms_uri.'&amp;isStudentView='.$_GET['isStudentView'];
				}
				return '&nbsp;<a href="'.$_SESSION['gradebook_dest'].$prms_uri.'">'
				 		. $item->get_name()
				 		. '</a>'
				 		. ($item->is_course() ? ' &nbsp;[' . $item->get_course_code() . ']' : '');
			// evaluation
			case 'E' :

				// course/platform admin can go to the view_results page
				if (api_is_allowed_to_create_course()) {
					return '&nbsp;'
						. '<a href="gradebook_view_result.php?selecteval=' . $item->get_id() . '">'
						. $item->get_name()
						. '</a>';
				} elseif (ScoreDisplay :: instance()->is_custom()) {
					// students can go to the statistics page (if custom display enabled)
					return '&nbsp;'
						. '<a href="gradebook_statistics.php?selecteval=' . $item->get_id() . '">'
						. $item->get_name()
						. '</a>';
				} else {
					return $item->get_name();
				}
			// link
			case 'L' :
				$url = $item->get_link();				
				if (isset($url)) {
					$text = '&nbsp;<a href="' . $item->get_link() . '">'
							. $item->get_name()
							. '</a>';
				} else {
					$text = $item->get_name();
				}
				$text .= '&nbsp;[' . $item->get_type_name() . ']';
				$cc = $this->currentcat->get_course_code();
				if(empty($cc)) {
					$text .= '&nbsp;[<a href="'.api_get_path(REL_COURSE_PATH).$item->get_course_code().'/">'.$item->get_course_code().'</a>]';
				}
				return $text;	
		}
	}
	private function build_edit_column ($item) {
		switch ($item->get_item_type()) {
			// category
			case 'C' :
				return build_edit_icons_cat($item, $this->currentcat->get_id());
			// evaluation
			case 'E' :
				return build_edit_icons_eval($item, $this->currentcat->get_id());
			// link
			case 'L' :
				return build_edit_icons_link($item, $this->currentcat->get_id());				
			
		}
	}
}