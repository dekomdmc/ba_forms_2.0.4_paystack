<?php
/**
* @package   BaForms
* @author    Balbooa http://www.balbooa.com/
* @copyright Copyright @ Balbooa
* @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
*/ 

defined('_JEXEC') or die;
jimport('joomla.application.component.modeladmin');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
use Joomla\Registry\Registry;

class baformsModelForm extends JModelAdmin
{
    public function getTable($type = 'Forms', $prefix = 'formsTable', $config = array())
    {
        return JTable::getInstance($type, $prefix, $config);
    }

    public function getGoogleAuth($client_id, $scope)
    {
        $redirect = 'urn:ietf:wg:oauth:2.0:oob';
        $auth = 'https://accounts.google.com/o/oauth2/auth?response_type=code&redirect_uri=';
        $auth .= urlencode($redirect).'&client_id='.urlencode($client_id).'&scope=';
        $auth .= urlencode($scope).'&access_type=offline&approval_prompt=auto';

        return $auth;
    }
 
    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm($this->option . '.form', 'form', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }
 
        return $form;
    }

    public function installTemplate()
    {
        $str = file_get_contents('php://input');
        $data = json_decode($str);
        $imageData = $data->imageData;
        $method = $data->method;
        unset($data->imageData);
        unset($data->method);
        $db = JFactory::getDbo();
        $db->insertObject('#__baforms_templates', $data);
        $id = $db->insertid();
        $array = explode(',', $imageData);
        $method = $method;
        $content = $method($array[1]);
        JFile::write(JPATH_COMPONENT.'/assets/images/templates/'.$data->image, $content);
        print_r($id);exit;
    }

    public function getFormTemplates()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__baforms_templates')
            ->order('`title` ASC');
        $db->setQuery($query);
        $items = $db->loadObjectList();

        return $items;
    }

    public function getFormsTemplate($id)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('data')
            ->from('#__baforms_templates')
            ->where('id = '.$id);
        $db->setQuery($query);
        $data = $db->loadResult();

        $obj = json_decode($data);
        $object = new stdClass();
        $str = JFile::read(JPATH_ROOT.'/components/com_baforms/libraries/countries/countries.json');
        baformsHelper::$countries = json_decode($str);
        $array = baformsHelper::drawPages($obj->pages);
        list($object->html, $object->items) = $array;
        $object->items->navigation = json_decode($obj->navigation);
        $object->condition_logic = json_decode($obj->condition_logic);
        $data = json_encode($object);
        

        return $data;
    }

    public function createTemplate($id, $group)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('f.title, s.navigation, s.condition_logic')
            ->from('#__baforms_forms AS f')
            ->where('f.id = '.$id)
            ->leftJoin('#__baforms_forms_settings AS s ON s.form_id = f.id');
        $db->setQuery($query);
        $form = $db->loadObject();
        $form->pages = $this->getPages();
        foreach ($form->pages as $page) {
            $page->id = 0;
            $page->columns = array();
            $columns_order = json_decode($page->columns_order, true);
            foreach ($columns_order as $key) {
                $column = baformsHelper::getFormColumns($id, $key);
                $column->id = 0;
                $column->fields = baformsHelper::getFormItems($page->form_id, $column->key);
                foreach ($column->fields as $field) {
                    $field->id = 0;
                }
                $page->columns[] = $column;
            }
        }
        $data = json_encode($form);
        $filename = baformsHelper::replace($form->title);
        $doc = new DOMDocument('1.0');
        $doc->formatOutput = true;
        $root = $doc->createElement('template');
        $root = $doc->appendChild($root);
        
        $title = $doc->createElement('title');
        $title = $root->appendChild($title);
        $text = $doc->createTextNode($form->title);
        $text = $title->appendChild($text);

        $title = $doc->createElement('group');
        $title = $root->appendChild($title);
        $text = $doc->createTextNode($group);
        $text = $title->appendChild($text);

        $title = $doc->createElement('key');
        $title = $root->appendChild($title);
        $text = $doc->createTextNode($filename);
        $text = $title->appendChild($text);

        $title = $doc->createElement('image');
        $title = $root->appendChild($title);
        $text = $doc->createTextNode($group.'-'.$filename.'.png');
        $text = $title->appendChild($text);

        $title = $doc->createElement('data');
        $title = $root->appendChild($title);
        $text = $doc->createTextNode($data);
        $text = $title->appendChild($text);

        $bytes = $doc->save(JPATH_ROOT.'/tmp/'.$group.'-'.$filename.'.xml');
        print_r(JUri::root().'tmp/'.$group.'-'.$filename.'.xml');exit;
    }

    public function saveIntegration($id, $obj)
    {
        $db = JFactory::getDbo();
        if (is_object($obj->key)) {
            $obj->key = json_encode($obj->key);
        }
        $array = array('google_maps', 'stripe', 'paypal', 'twocheckout', 'authorize', 'liqpay', 'payupl',
            'cloudpayments', 'robokassa', 'mollie', 'payu_latam', 'yandex_kassa', 'redsys', 'payfast', 'paypal_sdk', 'paystack');
        if (in_array($obj->service, $array)) {
            $db->updateObject('#__baforms_api', $obj, 'id');
        } else if ($obj->service == 'zoho_crm') {
            $data = json_decode($obj->key);
            $object = new stdClass();
            $object->client_id = $data->client_id;
            $object->client_secret = $data->client_secret;
            $object->email = $data->email;
            $object->host = $data->host;
            $object->grant_token = $data->grant_token;
            $obj->key = json_encode($object);
            $db->updateObject('#__baforms_api', $obj, 'id');
            unset($data->client_id);
            unset($data->client_secret);
            unset($data->email);
            unset($data->host);
            unset($data->grant_token);
            $object = new stdClass();
            $object->id = $id;
            $object->zoho_crm_fields = json_encode($data);
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'campaign_monitor') {
            $data = json_decode($obj->key);
            $object = new stdClass();
            $object->api_key = $data->api_key;
            $object->client_id = $data->client_id;
            $obj->key = json_encode($object);
            $db->updateObject('#__baforms_api', $obj, 'id');
            unset($data->api_key);
            unset($data->client_id);
            $object = new stdClass();
            $object->id = $id;
            $object->campaign_monitor_fields = json_encode($data);
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'activecampaign') {
            $data = json_decode($obj->key);
            $object = new stdClass();
            $object->account = $data->account;
            $object->api_key = $data->api_key;
            $obj->key = json_encode($object);
            $db->updateObject('#__baforms_api', $obj, 'id');
            unset($data->account);
            unset($data->api_key);
            $object = new stdClass();
            $object->id = $id;
            $object->activecampaign_fields = json_encode($data);
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'getresponse') {
            $data = json_decode($obj->key);
            $object = new stdClass();
            $object->api_key = $data->api_key;
            $object->custom_fields = $data->custom_fields;
            $obj->key = json_encode($object);
            $db->updateObject('#__baforms_api', $obj, 'id');
            unset($data->api_key);
            unset($data->custom_fields);
            $object = new stdClass();
            $object->id = $id;
            $object->getresponse_fields = json_encode($data);
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'acymailing') {
            $object = new stdClass();
            $object->id = $id;
            $object->acym_fields_map = $obj->key;
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'telegram') {
            $object = new stdClass();
            $object->id = $id;
            $object->telegram_token = $obj->key;
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'mailchimp') {
            $data = json_decode($obj->key);
            $obj->key = $data->api_key;
            $db->updateObject('#__baforms_api', $obj, 'id');
            $object = new stdClass();
            $object->id = $id;
            $object->mailchimp_list_id = $data->list;
            unset($data->api_key);
            unset($data->list);
            $object->mailchimp_fields_map = json_encode($data);
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'pdf_submissions') {
            $object = new stdClass();
            $object->id = $id;
            $object->pdf_submissions = $obj->key;
            $db->updateObject('#__baforms_forms', $object, 'id');
        } else if ($obj->service == 'google_sheets') {
            $data = json_decode($obj->key);
            $key = new stdClass();
            $key->client_id = $data->client_id;
            $key->client_secret = $data->client_secret;
            $key->code = $data->code;
            $key->accessToken = $data->accessToken;
            $obj->key = json_encode($key);
            $db->updateObject('#__baforms_api', $obj, 'id');
            unset($data->client_id);
            unset($data->client_secret);
            unset($data->code);
            unset($data->accessToken);
            $object = new stdClass();
            $object->columns = new stdClass();
            $object->spreadsheet = $data->spreadsheet;
            $object->worksheet = $data->worksheet;
            foreach ($data as $key => $value) {
                if ($key != 'spreadsheet' && $key != 'worksheet') {
                    $object->columns->{$key} = $value;
                }
            }
            $obj = new stdClass();
            $obj->id = $id;
            $obj->google_sheets = json_encode($object);
            $db->updateObject('#__baforms_forms', $obj, 'id');
        } else if ($obj->service == 'google_drive') {
            $data = json_decode($obj->key);
            $key = new stdClass();
            $key->client_id = $data->client_id;
            $key->client_secret = $data->client_secret;
            $key->code = $data->code;
            $key->accessToken = $data->accessToken;
            $obj->key = json_encode($key);
            $db->updateObject('#__baforms_api', $obj, 'id');
            unset($data->client_id);
            unset($data->client_secret);
            unset($data->code);
            unset($data->accessToken);
            $obj = new stdClass();
            $obj->id = $id;
            $obj->google_drive = json_encode($data);
            $db->updateObject('#__baforms_forms', $obj, 'id');
        }
    }

    public function getResponseObject($obj)
    {
        $object = new stdClass();
        $object->id = $obj->id;
        $object->key = $obj->key;

        return $object;
    }

    public function formsSave($obj)
    {
        $db = JFactory::getDbo();
        $db->updateObject('#__baforms_forms_settings', $obj->settings, 'form_id');
        $db->updateObject('#__baforms_forms', $obj->form, 'id');
        $ids = array(0);
        $response = new stdClass();
        $response->items = array();
        foreach ($obj->pages as $page) {
            $page->columns_order = json_encode($page->columns_order);
            if (!empty($page->id)) {
                $db->updateObject('#__baforms_pages', $page, 'id');
            } else {
                $db->insertObject('#__baforms_pages', $page);
                $page->id = $db->insertid();
                $response->items[] = $this->getResponseObject($page);
            }
            $ids[] = $page->id;
        }
        $cid = implode(', ', $ids);
        $query = $db->getQuery(true)
            ->delete('#__baforms_pages')
            ->where('form_id = '.$obj->id)
            ->where('id NOT IN ('.$cid.')');
        $db->setQuery($query)
            ->execute();
        $ids = array(0);
        foreach ($obj->columns as $column) {
            if (!empty($column->id)) {
                $db->updateObject('#__baforms_columns', $column, 'id');
            } else {
                $db->insertObject('#__baforms_columns', $column);
                $column->id = $db->insertid();
                $response->items[] = $this->getResponseObject($column);
            }
            $ids[] = $column->id;
        }
        $cid = implode(', ', $ids);
        $query = $db->getQuery(true)
            ->delete('#__baforms_columns')
            ->where('form_id = '.$obj->id)
            ->where('id NOT IN ('.$cid.')');
        $db->setQuery($query)
            ->execute();
        $ids = array(0);
        foreach ($obj->items as $item) {
            $item->options = json_encode($item->options);
            if (!empty($item->id)) {
                $db->updateObject('#__baforms_items', $item, 'id');
            } else {
                $db->insertObject('#__baforms_items', $item);
                $item->id = $db->insertid();
                $response->items[] = $this->getResponseObject($item);
            }
            $ids[] = $item->id;
        }
        $cid = implode(', ', $ids);
        $query = $db->getQuery(true)
            ->delete('#__baforms_items')
            ->where('form_id = '.$obj->id)
            ->where('id NOT IN ('.$cid.')');
        $db->setQuery($query)
            ->execute();
        $response->text = JText::_('FORM_SAVED');
        $str = json_encode($response);
        echo $str;
        exit;
    }

    public function getRecaptchaData()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('params, enabled, element')
            ->from('#__extensions')
            ->where('element = '.$db->quote('recaptcha').' OR element = '.$db->quote('recaptcha_invisible'))
            ->where('folder = '.$db->quote('captcha'))
            ->where('type = '.$db->quote('plugin'));
        $db->setQuery($query);
        $list = $db->loadObjectList();
        $data = new stdClass();
        $data->data = new stdClass();
        foreach ($list as $value) {
            if ($value->enabled == 1) {
                $obj = new Registry();
                $obj->loadString($value->params);
                $object = new stdClass();
                $object->public_key = $obj->get('public_key', '');
                $object->private_key = $obj->get('private_key', '');
                $object->theme = $obj->get('theme2', '');
                $object->size = $obj->get('size', '');
                $object->badge = $obj->get('badge', '');
            } else {
                $object = null;
            }
            $data->{$value->element} = $object;
        }
        $str = json_encode($data);

        return $str;
    }

    public function getFormShortCodes()
    {
        $user = JFactory::getUser();
        $obj = new stdClass();
        $obj->{'[Username]'} = $user->username;
        $obj->{'[User Name]'} = $user->name;
        $obj->{'[User Email]'} = $user->email;
        $obj->{'[User IP Address]'} = $_SERVER['REMOTE_ADDR'];
        $obj->{'[Date]'} = JHtml::date(time(), 'j F Y');
        $obj->{'[Time]'} = date('H:i:s');
        $obj->{'[Time AM / PM]'} = date('h:i:s A');
        $obj->{'[Submission ID]'} = '';

        return $obj;
    }

    public function getFormOptions()
    {
        $obj = new stdClass();
        $dir = JPATH_COMPONENT.'/assets/json/';
        $files = JFolder::files($dir);
        foreach ($files as $value) {
            $str = JFile::read($dir.$value);
            $key = str_replace('.json', '', $value);
            $obj->{$key} = json_decode($str);
        }

        return $obj;
    }

    public function getGoogleFonts()
    {
        $file = JPATH_COMPONENT.'/assets/libraries/google-fonts/font.json';
        $str = JFile::read($file);

        return $str;
    }

    public function getFields()
    {
        $db = JFactory::getDbo();
        $input = JFactory::getApplication()->input;
        $id = $input->get('id', 0, 'int');
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__baforms_items')
            ->where('id = '.$id);
        $db->setQuery($query);
        $item = $db->loadObject();
        
        return $item;
    }

    public function getColumns()
    {
        $db = JFactory::getDbo();
        $input = JFactory::getApplication()->input;
        $id = $input->get('id', 0, 'int');
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__baforms_columns')
            ->where('id = '.$id);
        $db->setQuery($query);
        $item = $db->loadObject();
        
        return $item;
    }

    public function getPages()
    {
        $db = JFactory::getDbo();
        $input = JFactory::getApplication()->input;
        $id = $input->get('id', 0, 'int');
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__baforms_pages')
            ->where('form_id = '.$id)
            ->order('`order_index` ASC');
        $db->setQuery($query);
        $item = $db->loadObjectList();
        
        return $item;
    }

    public function getItem($pk = NULL)
    {
        $db = JFactory::getDbo();
        $input = JFactory::getApplication()->input;
        $id = $input->get('id', 0, 'int');
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__baforms_forms')
            ->where('id = '.$id);
        $db->setQuery($query);
        $item = $db->loadObject();
        
        return $item;
    }

    public function createForm($title)
    {
        $id = 0;
        if (!empty($title)) {
            $db = JFactory::getDbo();
            $obj = new stdClass();
            $obj->title = $title;
            $db->insertObject('#__baforms_forms', $obj);
            $id = $db->insertid();
            $obj = new stdClass();
            $obj->key = 'ba-form-page-1';
            $obj->title = 'Page 1';
            $obj->form_id = $id;
            $obj->columns_order = '["bacolumn-1"]';
            $db->insertObject('#__baforms_pages', $obj);
            $obj = new stdClass();
            $obj->form_id = $id;
            $obj->parent = 'ba-form-page-1';
            $obj->key = 'bacolumn-1';
            $obj->width = 'span12';
            $db->insertObject('#__baforms_columns', $obj);
        }

        return $id;
    }

    public function restore($cid)
    {
        $db = JFactory::getDbo();
        foreach ($cid as $id) {
            $obj = new stdClass();
            $obj->id = $id;
            $obj->published = 1;
            $db->updateObject('#__baforms_forms', $obj, 'id');
        }
    }

    public function exportForms()
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__baforms_forms');
        $db->setQuery($query);
        $array = $db->loadObjectList();
        $cid = array();
        foreach ($array as $obj) {
            $cid[] = $obj->id;
        }
        $this->exportForm($cid);
    }

    public function createDOMBranch($doc, $db, $table, $id, $key, $parent, $element)
    {
        $query = $db->getQuery(true);
        $query->select('*')
            ->from($table)
            ->where($key.' = ' .$id);
        $db->setQuery($query);
        $objects = $db->loadObjectList();
        foreach ($objects as $key => $object) {
            $item = $doc->createElement($element);
            $item = $parent->appendChild($item);
            foreach ($object as $key => $value) {
                $title = $doc->createElement($key);
                $title = $item->appendChild($title);
                $data = $doc->createTextNode($value);
                $data = $title->appendChild($data);
            }
        }
    }

    public function exportForm($cid)
    {
        $db = JFactory::getDbo();
        $doc = new DOMDocument('1.0');
        $doc->formatOutput = true;
        $root = $doc->createElement('baforms');
        $root = $doc->appendChild($root);
        foreach ($cid as $id) {
            $baform = $doc->createElement('baform');
            $baform = $root->appendChild($baform);
            $this->createDOMBranch($doc, $db, '#__baforms_forms', $id, 'id', $baform, 'form');
            $this->createDOMBranch($doc, $db, '#__baforms_forms_settings', $id, 'form_id', $baform, 'settings');
            $columns = $doc->createElement('columns');
            $columns = $baform->appendChild($columns);
            $this->createDOMBranch($doc, $db, '#__baforms_columns', $id, 'form_id', $columns, 'column');
            $items = $doc->createElement('items');
            $items = $baform->appendChild($items);
            $this->createDOMBranch($doc, $db, '#__baforms_items', $id, 'form_id', $items, 'item');
            $pages = $doc->createElement('pages');
            $pages = $baform->appendChild($pages);
            $this->createDOMBranch($doc, $db, '#__baforms_pages', $id, 'form_id', $pages, 'page');
        }
        $file = '/tmp/forms.xml';
        $bytes = $doc->save(JPATH_ROOT.$file);
        if ($bytes) {
            echo new JResponseJson(true, JPATH_ROOT.$file);
        } else {
            echo new JResponseJson(false, '', true);
        }
        jexit();
    }

    public function importTable($db, $table, $data, $id = 0)
    {
        $str = json_encode($data);
        $obj = json_decode($str);
        $obj->id = 0;
        if (!empty($id)) {
            $obj->form_id = $id;
        }
        $db->insertObject($table, $obj);
    }

    public function importForms($xml)
    {
        $db = JFactory::getDbo();
        foreach ($xml->baform as $baform) {
            $this->importTable($db, '#__baforms_forms', $baform->form);
            $id = $db->insertid();
            $this->importTable($db, '#__baforms_forms_settings', $baform->settings, $id);
            foreach ($baform->pages->page as $page) {
                $this->importTable($db, '#__baforms_pages', $page, $id);
            }
            foreach ($baform->columns->column as $column) {
                $this->importTable($db, '#__baforms_columns', $column, $id);
            }
            foreach ($baform->items->item as $item) {
                $this->importTable($db, '#__baforms_items', $item, $id);
            }
        }
    }

    public function getBaitems()
    {
        $input = JFactory::getApplication()->input;
        $id = $input->get('id');
        if (!empty($id)) {
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);
            $query->select('id, settings')
                ->from('#__baforms_items')
                ->where('`form_id` = ' .$id);
            $db->setQuery($query);
            $result = $db->loadObjectList();
        } else {
            $result = new stdClass();
        }
        
        return $result;
    }

    public function getSheetsWrapper($client_id, $client_secret)
    {
        require_once JPATH_ROOT.'/components/com_baforms/libraries/wrappers/sheets.php';
        $sheets = new sheets($client_id, $client_secret);

        return $sheets;
    }

    public function getDriveWrapper($client_id, $client_secret)
    {
        require_once JPATH_ROOT.'/components/com_baforms/libraries/wrappers/drive.php';
        $drive = new drive($client_id, $client_secret);

        return $drive;
    }

    public function getWorkSheets($accessToken, $spreadsheet, $client_id, $client_secret)
    {
        $sheets = $this->getSheetsWrapper($client_id, $client_secret);
        $worksheets = $sheets->getWorkSheets($accessToken, $spreadsheet->title);
        $str = json_encode($worksheets);

        return $str;
    }

    public function getWorkSheetsColumns($accessToken, $spreadsheet, $worksheet, $client_id, $client_secret)
    {
        $sheets = $this->getSheetsWrapper($client_id, $client_secret);
        $columns = $sheets->getWorkSheetsColumns($accessToken, $spreadsheet->title, $worksheet->title);
        $str = json_encode($columns);

        return $str;
    }

    public function createDriveToken($client_id, $client_secret, $code)
    {
        $token = '';
        if (!empty($code)) {
            $drive = $this->getDriveWrapper($client_id, $client_secret);
            $token = $drive->createAccessToken($code);
            if ($token != 'INVALID_TOKEN') {
                $obj = new stdClass();
                $obj->code = $code;
                $obj->accessToken = $token;                
                $obj->folders = $drive->getFolders($token);
                $token = json_encode($obj);
            }
        }

        return $token;
    }

    public function getDriveFolders($client_id, $client_secret, $token)
    {
        $drive = $this->getDriveWrapper($client_id, $client_secret);
        $folders = $drive->getFolders($token);
        $str = json_encode($folders);

        return $str;
    }

    public function createSheetsToken($client_id, $client_secret, $code)
    {
        $token = '';
        if (!empty($code)) {
            $sheets = $this->getSheetsWrapper($client_id, $client_secret);
            $token = $sheets->createAccessToken($code);
            if ($token != 'SHEETS_INVALID_TOKEN') {
                $obj = new stdClass();
                $obj->code = $code;
                $obj->accessToken = $token;                
                $obj->sheets = $sheets->getSpreadsheet($token);
                $token = json_encode($obj);
            }
        }

        return $token;
    }

    public function getSpreadSheets($client_id, $client_secret, $token)
    {
        $sheets = $this->getSheetsWrapper($client_id, $client_secret);
        $spreadsheet = $sheets->getSpreadsheet($token);
        $data = json_encode($spreadsheet);

        return $data;
    }

    private function getEmbed($id)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true)
            ->select("submit_embed")
            ->from("#__baforms_forms")
            ->where("id=" . $id);
        $db->setQuery($query);
        $embed = $db->loadResult();

        return $embed;
    }

    
    
    public function delete(&$pks)
    {
        $db = JFactory::getDbo();
        foreach ($pks as $id) {
            $this->deleteSecondaryTables($db, '#__baforms_forms', $id, 'id');
            $this->deleteSecondaryTables($db, '#__baforms_forms_settings', $id, 'form_id');
            $this->deleteSecondaryTables($db, '#__baforms_pages', $id, 'form_id');
            $this->deleteSecondaryTables($db, '#__baforms_columns', $id, 'form_id');
            $this->deleteSecondaryTables($db, '#__baforms_items', $id, 'form_id');
        }

        return true;
    }

    public function deleteSecondaryTables($db, $table, $id, $key)
    {
        $query = $db->getQuery(true)
            ->delete($table)
            ->where($key.' = '.$id);
        $db->setQuery($query)
            ->execute();
    }

    public function getNewTitle($title)
    {
        $table = $this->getTable();
        while ($table->load(array('title' => $title))) {
            $title = JString::increment($title);
        }

        return $title;
    }
    
    public function duplicate(&$pks)
    {
        $db = $this->getDbo();
        foreach ($pks as $id) {
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__baforms_forms')
                ->where('id = '.$id);
            $db->setQuery($query);
            $form = $db->loadObject();
            $form->id = 0;
            $form->title = $this->getNewTitle($form->title);
            $db->insertObject('#__baforms_forms', $form);
            $form_id = $db->insertid();
            $this->duplicateSecondaryTables($db, '#__baforms_forms_settings', $id, $form_id);
            $this->duplicateSecondaryTables($db, '#__baforms_pages', $id, $form_id);
            $this->duplicateSecondaryTables($db, '#__baforms_columns', $id, $form_id);
            $this->duplicateSecondaryTables($db, '#__baforms_items', $id, $form_id);
        }
    }

    public function duplicateSecondaryTables($db, $table, $id, $form_id)
    {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($table)
            ->where('form_id = '.$id);
        $db->setQuery($query);
        $array = $db->loadObjectList();
        foreach ($array as $obj) {
            $obj->form_id = $form_id;
            $obj->id = 0;
            $db->insertObject($table, $obj);
        }
    }
}