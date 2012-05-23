<?php

	class Db_FilterBehavior extends Phpr_ControllerBehavior
	{
		public $filter_list_title = 'Filters';
		public $filter_filters = array();
		public $filter_switchers = array();
		public $filter_ignore_filter = null;
		public $filter_prompt = 'Please select records.';
		public $filter_desc_max_len = 100;
		public $filter_onApply = null;
		public $filter_onRemove = null;

		public function __construct($controller)
		{
			parent::__construct($controller);

			$this->addEventHandler('onFilterLoadForm');
			$this->addEventHandler('onFilterApply');
			$this->addEventHandler('onFilterRemove');
			$this->addEventHandler('onFilterApplySwitchers');

			$this->_controller->addCss('/phproad/modules/db/behaviors/db_filterbehavior/resources/css/filters.css?'.module_build('core'));
			$this->_controller->addJavaScript('/phproad/modules/db/behaviors/db_filterbehavior/resources/javascript/filters.js?'.module_build('core'));
			
			if (post('filter_id_value'))
			{
				$filterId = post('filter_id_value');
				$filterObj = $this->getFilterObj($filterId);
				$modelObj = $this->createModelObj($filterObj);
				$filterColumns = Phpr_Util::splat($filterObj->list_columns);
				
				$searchFields = $filterColumns;
				foreach ($searchFields as $index=>&$field)
					$field = "@".$field;

				$this->_controller->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_filterbehavior/partials/_filter_body_control.htm';
				$this->_controller->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_filterbehavior/partials/_filter_head_control.htm';
				
				$is_sliding_list = $modelObj->isExtendedWith('Db_Act_As_Tree');

				$this->_controller->list_options['list_model_class'] = get_class($modelObj);
				$this->_controller->list_options['list_no_setup_link'] = true;
				$this->_controller->list_options['list_columns'] = $filterColumns;
				$this->_controller->list_options['list_render_as_sliding_list'] = $is_sliding_list;
				$this->_controller->list_options['list_custom_prepare_func'] = 'filterPrepareData';
				$this->_controller->list_options['list_custom_body_cells'] = PATH_APP.'/phproad/modules/db/behaviors/db_filterbehavior/partials/_filter_body_control.htm';
				$this->_controller->list_options['list_custom_head_cells'] = PATH_APP.'/phproad/modules/db/behaviors/db_filterbehavior/partials/_filter_head_control.htm';
				$this->_controller->list_options['list_search_fields'] = $searchFields;
				$this->_controller->list_options['list_search_prompt'] = 'search';
				$this->_controller->list_options['list_no_form'] = true;
				$this->_controller->list_options['list_record_url'] = null;
				$this->_controller->list_options['list_items_per_page'] = 6;
				$this->_controller->list_options['list_search_enabled'] = true;
				$this->_controller->list_options['list_name'] = $this->filterListName($modelObj);
				$this->_controller->list_options['filter_id'] = $filterId;
				$this->_controller->list_options['list_reuse_model'] = false;
				$this->_controller->list_options['list_no_js_declarations'] = true;
				$this->_controller->list_options['list_scrollable'] = false;
				$this->_controller->list_name = $this->filterListName($modelObj);
				$this->_controller->list_record_url = null;

				$this->_controller->listApplyOptions($this->_controller->list_options);
			}
		}

		/**
		 *
		 * Public methods - you may call it from your views
		 *
		 */
		
		/**
		 * Renders a filter settings UI
		 */
		public function filterRender()
		{
			$this->loadFilterSettings();
			$this->renderPartial('filter_settings');
		}

		public function filterRenderPartial($view, $params = array())
		{
			$this->renderPartial($view, $params);
		}
		
		public function filterPrepareData($model, $options)
		{
			$filterObj = $this->getFilterObj($options['filter_id']);
			return $this->createModelObj($filterObj);
		}

		/*
		 * Filtering methods - call it from controller to filter data
		 */
		
		public function filterApplyToModel($model, $context = null)
		{
			$filters = Db_UserParameters::get($this->getFiltersName(), null, array());
			foreach ($filters as $filter_id=>$filterSet)
			{
				if (array_key_exists($filter_id, $this->_controller->filter_filters))
					$this->getFilterObj($filter_id)->applyToModel($model, array_keys($filterSet), $context);
			}
				
			$swicher_values = array();
			$enabled_switchers = Db_UserParameters::get($this->getFiltersName('switchers'), null, array());
			foreach ($this->_controller->filter_switchers as $switcher_id=>$switcher_info)
			{
				$switcher_obj = $this->getSwitcherObj($switcher_id);
				
				if (in_array($switcher_id, $enabled_switchers))
					$switcher_obj->applyToModel($model, true, $context);
				else
					$switcher_obj->applyToModel($model, false, $context);
					
			}

			return $model;
		}

		public function filterAsString($context = null)
		{
			$filters = Db_UserParameters::get($this->getFiltersName(), null, array());
			$result = null;
			foreach ($filters as $filter_id=>$filterSet)
				$result .= ' '.$this->getFilterObj($filter_id)->asString(array_keys($filterSet), $context);
				
			return $result;
		}
		
		public function filtersGetKeys($filter_id, $context = null)
		{
			$filters = Db_UserParameters::get($this->getFiltersName(), null, array());
			
			if (!array_key_exists($filter_id, $filters))
				return array();

			return array_keys($filters[$filter_id]);
		}

		public function filterReset()
		{
			Db_UserParameters::set($this->getFiltersName(), array());
		}

		public function filterGetKeys($filterId)
		{
			$filters = Db_UserParameters::get($this->getFiltersName(), null, array());
			if (!array_key_exists($filterId, $filters))
				return array();

			return array_keys($filters[$filterId]);
		}

		/*
		 * Event handlers
		 */

		public function onFilterLoadForm()
		{
			try
			{
				$id = post('id');
				if (!array_key_exists($id, $this->_controller->filter_filters))
					$this->viewData['not_found'] = true;
				else 
				{
					$this->viewData['filterInfo'] = $filterInfo = $this->_controller->filter_filters[$id];
					$this->viewData['filterId'] = $id;
					$this->viewData['filter_new'] = !post('existing');

					$filter_class = $filterInfo['class_name'];
					$obj = new $filter_class();
					$this->viewData['filter_obj'] = $obj;

					$model_class = $obj->model_class_name;
					$model = new $model_class();
					$this->viewData['model'] = $model;

					$settings = $this->loadFilterSettings();

					$checkedRecords = array();
					if (array_key_exists($id, $settings))
						$checkedRecords = array_keys($settings[$id]);

					if ($checkedRecords)
					{
						$listColumns = Phpr_Util::splat($obj->list_columns);
						$primary_key = $model->primary_key;
						$this->viewData['filter_checked_records'] = $model->where("$primary_key in (?)", array($checkedRecords))->order($listColumns[0])->find_all();
					}
				}

				$this->renderPartial('filter_form');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function onFilterApply()
		{
			try
			{
				$filterId = post('filterId');
				$ids = Phpr_Util::splat(post('filter_ids', array()));

				if (!count($ids))
				{
					if (post('filter_existing'))
						throw new Phpr_ApplicationException('Please select at least one record, or click Cancel Filter button to cancel the filter.');
					else
						throw new Phpr_ApplicationException('Please select at least one record.');
				}

				$filters = Db_UserParameters::get($this->getFiltersName(), null, array());
				$filterObj = $this->getFilterObj($filterId);
				$filterColumns = Phpr_Util::splat($filterObj->list_columns);
				
				$modelObj = $this->createModelObj($filterObj);
//				$records = $modelObj->find_all();
				$recordNum = $modelObj->requestRowCount();

//				if (!$modelObj->isExtendedWith('Db_Act_As_Tree'))
//					$recordNum = $records->count;
				// else
				// {
				// 	$recordNum = 0;
				// 	foreach ($records as $record)
				// 	{
				// 		if (!$record->list_children()->count)
				// 			$recordNum++;
				// 	}
				// }

				if ($recordNum == count($ids) && $this->filterCancelIfAll($filterId))
				{
					if (array_key_exists($filterId, $filters))
					{
						unset($filters[$filterId]);
						Db_UserParameters::set($this->getFiltersName(), $filters);
					}
				} else 
				{
					if (count($ids))
						$records = $modelObj->where($modelObj->table_name.'.id in (?)', array($ids))->find_all();
					else
						$records = array();
					
					$recordMap = array();
					foreach ($records as $record)
						$recordMap[$record->get_primary_key_value()] = $record->{$filterColumns[0]};

					$filterSet = array();
					foreach ($ids as $id)
					{
						if (array_key_exists($id, $recordMap))
							$filterSet[$id] = $recordMap[$id];
					}

					$filters[$filterId] = $filterSet;
					Db_UserParameters::set($this->getFiltersName(), $filters);
				}

				$this->loadFilterSettings();
				$this->renderPartial('filter_settings_content');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function onFilterApplySwitchers()
		{
			try
			{
				$switchers = array_keys(post('filter_switchers', array()));
				Db_UserParameters::set($this->getFiltersName('switchers'), $switchers);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		public function onFilterRemove()
		{
			try
			{
				$filterId = post('filterId');
				$filters = Db_UserParameters::get($this->getFiltersName(), null, array());
				
				if (array_key_exists($filterId, $filters))
				{
					unset($filters[$filterId]);
					Db_UserParameters::set($this->getFiltersName(), $filters);
				}

				$this->loadFilterSettings();
				$this->renderPartial('filter_settings_content');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/**
		 *
		 * Private methods - used by the behavior
		 *
		 */

		private function loadFilterSettings()
		{
			$filters = Db_UserParameters::get($this->getFiltersName(), null, array());
			$switchers = Db_UserParameters::get($this->getFiltersName('switchers'), null, array());
			$this->viewData['filterCheckedSwitchers'] = $switchers;
			return $this->viewData['filterSettingsInfo'] = $filters;
		}
		
		private function createModelObj($filterObj)
		{
			return $filterObj->prepareListData();
		}

		private function getFilterObj($id)
		{
			if (!array_key_exists($id, $this->_controller->filter_filters))
				throw new Phpr_ApplicationException("Filter '$id' not found");

			$className = $this->_controller->filter_filters[$id]['class_name'];
			return new $className();
		}

		private function getSwitcherObj($id)
		{
			if (!array_key_exists($id, $this->_controller->filter_switchers))
				throw new Phpr_ApplicationException("Switcher '$id' not found");

			$className = $this->_controller->filter_switchers[$id]['class_name'];
			if (class_exists($className))
				return new $className();
				
			return null;
		}
		
		private function filterCancelIfAll($id)
		{
			if (!array_key_exists($id, $this->_controller->filter_filters))
				throw new Phpr_ApplicationException("Filter '$id' not found");

			$filterInfo = $this->_controller->filter_filters[$id];
			return isset($filterInfo['cancel_if_all']) ? $filterInfo['cancel_if_all'] : true;
		}

		private function getFiltersName($property_set = null)
		{
			return get_class($this->_controller).'_filters'.$property_set;
		}
		
		public function filterListName($model)
		{
			return get_class($this->_controller).'_filterlist_'.get_class($model);
		}
	}

?>