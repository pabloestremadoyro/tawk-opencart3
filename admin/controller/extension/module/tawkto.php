<?php
/**
 * @package Tawk.to Integration
 * @author Tawk.to
 * @copyright (C) 2014- Tawk.to
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

class ControllerExtensionModuleTawkto extends Controller {
    private $error = array();

    private function setup() {
        $this->load->language('extension/module/tawkto');

        $this->load->model('setting/setting');
        $this->load->model('design/layout');
        $this->load->model('setting/store');
        $this->load->model('localisation/language');

        //calling layout enable again ensures, that even if new
        //layouts are added widget will show up on those
        //new layouts
        $this->enableAllLayouts();
    }

    public function index() {
        $this->setup();

        $data = $this->setupIndexTexts();

        // get current store and load tawk.to options
        $store_id = 0;
        $stores = $this->model_setting_store->getStores();
        if (!empty($stores)) {
            foreach ($stores as $store) {
                if ($this->config->get('config_url') == $store['url']) {
                    $store_id = intval($store['store_id']);
                }
            }
        }

		
//**********************************************************************************************		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_tawkto', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}
		
		
		if (!isset($this->request->get['module_id'])) {
			$data['action'] = $this->url->link('extension/module/tawkto', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/tawkto', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}
//**********************************************************************************************		
				
		
		
        $data['base_url']   = $this->getBaseUrl();
        $data['iframe_url'] = $this->getIframeUrl();
        $data['hierarchy']  = $this->getStoreHierarchy();
        $data['url'] = array(
                'set_widget_url' => $this->url->link('extension/module/tawkto/setwidget', '', 'SSL') . '&user_token=' . $this->session->data['user_token'],
                'remove_widget_url' => $this->url->link('extension/module/tawkto/removewidget', '', 'SSL') . '&user_token=' . $this->session->data['user_token'],
                'set_options_url' => $this->url->link('extension/module/tawkto/setoptions', '', 'SSL') . '&user_token=' . $this->session->data['user_token']
            );

        $data['widget_config']  = $this->getConfig($store_id);
        $data['same_user'] = true;
        if (isset($data['widget_config']['user_id'])) {
            $data['same_user']  = ($data['widget_config']['user_id']==$this->session->data['user_id']);
        }
        
        $data['display_opts']  = $this->getDisplayOpts($store_id);
        $data['store_id']  = $store_id;
        $data['store_layout_id']  = $store_id; // set default to 0

        $data['header']      = $this->load->controller('common/header');
        $data['footer']      = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');
		
		$widget_config = $data['widget_config'];
		$display_opts = $data['display_opts'];
		$data['widgetconfig'] = (!is_null($widget_config['page_id']))?$widget_config['page_id']:0;
		$data['widgetconfigid'] = (!is_null($widget_config['widget_id']))?$widget_config['widget_id']:0;
		
		$data['alwaysdisplay'] = $display_opts['always_display'];
		
		$data['hideoncustom'] = !empty($display_opts['hide_oncustom']);
		if ($data['hideoncustom']){
		$data['whitelist'] = json_decode($display_opts['hide_oncustom']);
		$textowl = "";
		foreach ($data['whitelist'] as $line){
		$textowl .= $line . "\n";	
		}
		$data['whitelist'] = $textowl;
		}
		else
		{
		$data['whitelist'] = "";	
		}
		
		$data['showonfrontpage'] = $display_opts['show_onfrontpage'];
		
		$data['showoncategory'] = $display_opts['show_oncategory'];
		
		$data['showoncustom'] = !empty($display_opts['show_oncustom']);
		
      	if ($data['showoncustom']){
		$data['whitelist2'] = json_decode($display_opts['show_oncustom']);
		$textowl2 = "";
		foreach ($data['whitelist2'] as $line2){
		$textowl2 .= $line2 . "\n";	
		}
		$data['whitelist2'] = $textowl2;	
        }
		else
		{
		$data['whitelist2'] = "";	
		}
		$data['hierarchy2'] = json_encode($data['hierarchy']);
		
		$data['status'] = $this->config->get('module_tawkto_status');
		
        $this->response->setOutput($this->load->view('extension/module/tawkto', $data));
    }

    public function getConfig($store_id = 0)
    {
        $config = array(
                'page_id' => null,
                'widget_id' => null
            );
        
        $current_settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);
        if (isset($current_settings['module_tawkto_widget']['widget_config_'.$store_id])) {
            $config = $current_settings['module_tawkto_widget']['widget_config_'.$store_id];
        }
        
        return $config;
    }

    public function getDisplayOpts($store_id = 0)
    {
        $current_settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);

        $options = array(
                'always_display' => true,
                'hide_oncustom' => array(),
                'show_onfrontpage' => false,
                'show_oncategory' => false,
                'show_oncustom' => array()
            );
        if (isset($current_settings['module_tawkto_visibility'])) {
            $options = $current_settings['module_tawkto_visibility'];
            $options = json_decode($options,true);
        }
        
        return $options;
    }

    /**
     * Page id is mongodb object id and widget id is alpanumeric
     * string
     *
     * @return Boolean
     */
    private function validatePost() {
        return !empty($_POST['pageId']) && !empty($_POST['widgetId']) && isset($_POST['store'])
            && preg_match('/^[0-9A-Fa-f]{24}$/', $_POST['pageId']) === 1
            && preg_match('/^[a-z0-9]{1,50}$/i', $_POST['widgetId']) === 1;
    }

    public function setoptions() {
        header('Content-Type: application/json');

        $jsonOpts = array(
                'always_display' => false,
                'hide_oncustom' => array(),
                'show_onfrontpage' => false,
                'show_oncategory' => false,
                'show_onproduct' => false,
                'show_oncustom' => array(),
            );

        if (isset($_REQUEST['options']) && !empty($_REQUEST['options'])) {
            // $_REQUEST['options'] = urldecode($_REQUEST['options']);
            $options = explode('&', $_REQUEST['options']);
			

            foreach ($options as $post) {
                list($column, $value) = explode('=', $post);
                $column = str_ireplace('amp;', '', $column);
                switch ($column) {
					case 'hide_oncustom':
					    $value = urldecode($value);
                        $value = str_ireplace(["\r\n", "\r", "\n"], ',', $value);
						$value = str_ireplace(["\"","'","\\"], '', $value);
						if (!empty($value)){
						$value = explode(",", $value);							
						}
						else
						{
						$value = array();	
						}
						$value = (empty($value)||!$value)?array():$value;
                        $jsonOpts[$column] = json_encode($value);
                        break;
					case 'show_oncustom':
                        // replace newlines and returns with comma, and convert to array for saving
                        $value = urldecode($value);
                        $value = str_ireplace(["\r\n", "\r", "\n"], ',', $value);
						$value = str_ireplace(["\"","'","\\"], '', $value);
						if (!empty($value)){
						$value = explode(",", $value);							
						}
						else
						{
						$value = array();	
						}
                        $value = (empty($value)||!$value)?array():$value;
                        $jsonOpts[$column] = json_encode($value);
                        break;
                    case 'show_onfrontpage':
					$jsonOpts[$column] = ($value==1)?true:false;
                    break;
                    case 'show_oncategory':
					$jsonOpts[$column] = ($value==1)?true:false;
                    break;
                    case 'show_onproduct':
					$jsonOpts[$column] = ($value==1)?true:false;
                    break;
                    case 'always_display':
                    // default:
                        $jsonOpts[$column] = ($value==1)?true:false;
                        break;

						
                }
            }
        }

		
        $this->setup();
        $store_id = intval($_POST['store']);
		$this->model_setting_setting->editSettingValue('module_tawkto','module_tawkto_status', 1);
		$current_settings = $this->model_setting_setting->getSetting('module_tawkto', $store_id);
		$current_settings['module_tawkto_visibility'] = json_encode($jsonOpts);
        $this->model_setting_setting->editSetting('module_tawkto',$current_settings, $store_id);
		

        echo json_encode(array('success' => true));
        die();
    }

    public function setwidget() {
        header('Content-Type: application/json');

        if(!$this->validatePost() || !$this->validate()) {
            echo json_encode(array('success' => FALSE));
            die();
        }

        $this->setup();

        $currentSettings = $this->model_setting_setting->getSetting('module_tawkto');
        $currentSettings['module_tawkto_widget'] = isset($currentSettings['module_tawkto_widget']) ? $currentSettings['module_tawkto_widget'] : array();

        $currentSettings['module_tawkto_widget']['widget_config_'.$_POST['id']] = array(
            'page_id' => $_POST['pageId'],
            'widget_id' => $_POST['widgetId'],
            'user_id' => $this->session->data['user_id']
        );

        $this->model_setting_setting->editSetting('module_tawkto', $currentSettings);

        echo json_encode(array('success' => TRUE));
        die();
    }

    public function removewidget() {
        header('Content-Type: application/json');

        if(!isset($_POST['id']) || !$this->validate()) {
            echo json_encode(array('success' => FALSE));
            die();
        }

        $this->setup();

        $currentSettings = $this->model_setting_setting->getSetting('module_tawkto');
        unset($currentSettings['module_tawkto_widget']['widget_config_'.$_POST['id']]);

        $this->model_setting_setting->editSetting('module_tawkto', $currentSettings);

        echo json_encode(array('success' => TRUE));
        die();
    }

    private function setupIndexTexts() {
        $this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/tawkto', 'user_token=' . $this->session->data['user_token'], true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/tawkto', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
		}

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['heading_title'] = $this->language->get('heading_title');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['text_installed'] = $this->language->get('text_installed');
        return $data;
    }

    /**
     * Module supports multistore structure, each store and
     * its languages, layouts can have different widgets
     *
     * @return Array
     */
    private function getStoreHierarchy() {
        $stores                = $this->model_setting_store->getStores();
        $this->layouts         = $this->model_design_layout->getLayouts();
        $this->languages       = $this->model_localisation_language->getLanguages();
        $settings              = $this->model_setting_setting->getSetting('tawkto');
        $this->currentSettings = isset($settings['tawkto_widget']) ? $settings['tawkto_widget'] : array();

        $hierarchy = array();

        // we need to remove childs as these prevent us from monitoring user
        // and user's custom attributes as he/she navigates in store
        $hierarchy[] = array(
            'id'      => '0',
            'name'    => 'Default store',
            'current' => $this->getCurrentSettingsFor('0'),
            // 'childs'  => $this->getLanguageHierarchy('0')
            'childs'  => array()
        );

        foreach($stores as $store) {
            $hierarchy[] = array(
                'id'      => $store['store_id'],
                'name'    => $store['name'],
                'current' => $this->getCurrentSettingsFor($store['store_id']),
                // 'childs'  => $this->getLanguageHierarchy($store['store_id'])
                'childs'  => array()
            );
        }

        return $hierarchy;
    }

    /**
     * Each store can have more than one language
     * and this module allows to change widget for
     * each language separately
     *
     * @param  String $parent
     * @return Array
     */
    private function getLanguageHierarchy($parent) {
        $return = array();

        foreach($this->languages as $code => $details) {
            $return[] = array(
                'id'      => $parent . '_' . $details['language_id'],
                'name'    => $details['name'],
                'current' => $this->getCurrentSettingsFor($parent.'_'.$details['language_id']),
                'childs'  => $this->getLayoutHierarchy($parent.'_'.$details['language_id'])
            );
        }

        return $return;
    }

    /**
     * Builds layout list with current populating that with correct
     * value based on store and language, this is the last level
     *
     * @param  String $parent
     * @return Array
     */
    private function getLayoutHierarchy($parent) {
        $return = array();

        foreach ($this->layouts as $layout) {
            $return[] = array(
                'id'      => $parent . '_' . $layout['layout_id'],
                'name'    => $layout['name'],
                'childs'  => array(),
                'current' => $this->getCurrentSettingsFor($parent.'_'.$layout['layout_id'])
            );
        }

        return $return;
    }

    /**
     * Will retrieve widget settings for supplied item in hierarchy
     * It can be store, store + language or store+language+layout
     *
     * @param  Int   $id
     * @return Array
     */
    private function getCurrentSettingsFor($id) {
        if(isset($this->currentSettings['module_tawkto_widget']['widget_config_'.$id])) {
            $settings = $this->currentSettings['module_tawkto_widget']['widget_config_'.$id];

            return array(
                'pageId'   => $settings['page_id'],
                'widgetId' => $settings['widget_id']
            );
        } else {
            return array();
        }
    }

    private function getIframeUrl() {
        $settings = $this->model_setting_setting->getSetting('module_tawkto');

        return $this->getBaseUrl()
            .'/generic/widgets/'
            .'?selectType=singleIdSelect'
            .'&selectText=Store';
    }

    private function getBaseUrl() {
        return 'https://plugins.tawk.to';
    }

    public function install() {
        $this->setup();
		$this->model_setting_setting->editSetting('module_tawkto', array("module_tawkto_status" => 1));
        $this->enableAllLayouts();
    }

    public function uninstall() {
        $this->setup();

        $this->model_setting_setting->deleteSetting('module_tawkto');
    }

    private function enableAllLayouts() {
        $layouts = $this->model_design_layout->getLayouts();

        foreach ($layouts as $layout) { //will enable tawk.to module in every page/layout there is
            $this->db->query("INSERT INTO " . DB_PREFIX . "layout_module (layout_id, code, position, sort_order)
                SELECT '" . $layout['layout_id'] . "', 'tawkto', 'content_bottom', '999' FROM dual
                WHERE NOT EXISTS (
                    SELECT layout_module_id
                    FROM " . DB_PREFIX . "layout_module
                    WHERE layout_id = '" . $layout['layout_id'] . "' AND code = 'tawkto'
                )
                LIMIT 1
            ");
        }
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/tawkto')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
