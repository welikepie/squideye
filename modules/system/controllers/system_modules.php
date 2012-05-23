<?

	class System_Modules extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		
		protected $access_for_groups = array(Users_Groups::admin);

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_page = 'modules';
			$this->app_module_name = 'System';
		}
		
		public function index()
		{
			$this->app_page_title = 'Modules & Updates';
			$this->viewData['active_modules'] = Core_ModuleManager::listModules();

			$this->viewData['disabled_modules'] = Core_ModuleManager::listModules(false, true);
		}
		
		protected function index_onUpdateForm()
		{
			try
			{
				
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('updates_check_form');
		}
		
		protected function index_onCheckForUpdates()
		{
			try
			{
				$update_data = Core_UpdateManager::create()->request_update_list();
				$this->viewData['update_list'] = $update_data['data'];
				$this->viewData['developer_license'] = isset($update_data['developer']) && $update_data['developer'];
			}
			catch (Exception $ex)
			{
				$this->viewData['error'] = $ex->getMessage();
			}
			
			$this->renderPartial('update_list');
		}
		
		protected function index_onApplyUpdates($ignore_eula = false)
		{
			try
			{
				if (!$ignore_eula && Core_EulaManager::pull())
					$this->renderPartial('eula');
				else {
					Core_UpdateManager::create()->update_application();

					Phpr::$session->flash['success'] = 'LemonStand has been successfully updated';
					Phpr::$response->redirect(url('system/modules'));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onContinueEula()
		{
			try
			{
				Core_EulaManager::commit();
				if (!post('force'))
					self::index_onApplyUpdates(true);
				else
					self::index_onForceUpdate(true);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onForceUpdate($ignore_eula = false)
		{
			try
			{
				if (!$ignore_eula && Core_EulaManager::pull())
					$this->renderPartial('eula', array('force'=>true));
				else {
					Core_UpdateManager::create()->update_application(false, true);

					Phpr::$session->flash['success'] = 'LemonStand has been successfully updated';
					Phpr::$response->redirect(url('system/modules'));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function update_license()
		{
			$this->app_page_title = 'Update license information';
			$this->viewData['license_change_num'] = Core_UpdateManager::create()->get_license_change_num();
		}
		
		protected function update_license_onApply()
		{
			try
			{
				Core_UpdateManager::create()->set_license_info($_POST);
				Phpr::$session->flash['success'] = 'The license information has been successfully updated. If you switched to a commerce license, please force update the application.';
				Phpr::$response->redirect(url('system/modules'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>