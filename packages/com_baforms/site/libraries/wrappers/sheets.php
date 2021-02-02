<?php
/**
* @package   BaForms
* @author    Balbooa http://www.balbooa.com/
* @copyright Copyright @ Balbooa
* @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
*/

defined('_JEXEC') or die;

function bf_gdata_startsWith($haystack, $needle) {
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

require_once JPATH_ROOT.'/components/com_baforms/libraries/google-sheets/google_sheets_client/Google/autoload.php';
$srcDir = realpath(JPATH_ROOT.'/components/com_baforms/libraries/google-sheets/google_sheets_client/');
set_include_path($srcDir . PATH_SEPARATOR . get_include_path());
spl_autoload_register(function ($class) {
    if (strpos($class, '\\') !== false && bf_gdata_startsWith($class, 'Google')) {
        include str_replace("\\", "/", $class) . '.php';
    }
});

class sheets
{
    private $client = null;

    public function __construct($client_id, $client_secret)
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Balbooa Google Drive Spreadsheets');
        $this->client->addScope(array('https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/drive',
            'https://spreadsheets.google.com/feeds'));
        $this->client->setClientId($client_id);
        $this->client->setClientSecret($client_secret);
        $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $this->client->setAccessType('offline');
    }

    public function getAuthentication()
    {
        $authUrl = $this->client->createAuthUrl();

        return $authUrl;
    }

    public function createAccessToken($code)
    {
        try {
            $accessToken = $this->client->authenticate($code);
        } catch (Exception $e) {
            $accessToken = 'SHEETS_INVALID_TOKEN';
        }

        return $accessToken;
    }

    public function getSpreadsheet($accessToken)
    {
        $sheets = array();
        $this->client->setAccessToken($accessToken);
        $token = json_decode($accessToken);
        try {
            if ($this->client->isAccessTokenExpired()) {
                $this->client->refreshToken($token->refresh_token);
                $token = json_decode($this->client->getAccessToken());
            }
            $oauth = new Google_Service_Oauth2($this->client);
            $userinfo = $oauth->userinfo->get();
            $request = new DefaultServiceRequest($token->access_token, $token->token_type);
            ServiceRequestFactory::setInstance($request);
            $service = new Google\Spreadsheet\SpreadsheetService();
            $spreadsheets = $service->getSpreadsheets();
        } catch (Exception $e) {
            return null;
        }
        if ($spreadsheets !== null) {
            foreach($spreadsheets as $sheet){
                $author = $sheet->getAuthor();
                if ($author != $userinfo->email) {
                    continue;
                }
                $obj = new stdClass();
                $obj->id = $sheet->getId();
                $obj->title = $sheet->getTitle();
                $sheets[$obj->id] = $obj;
            }
        }

        return $sheets;
    }

    public function getWorkSheets($accessToken, $title)
    {
        $worksheetsArray = array();
        $this->client->setAccessToken($accessToken);
        $token = json_decode($accessToken);
        if ($this->client->isAccessTokenExpired()) {
            $this->client->refreshToken($token->refresh_token);
            $token = json_decode($this->client->getAccessToken());
        }
        $request = new DefaultServiceRequest($token->access_token, $token->token_type);
        ServiceRequestFactory::setInstance($request);
        $service = new Google\Spreadsheet\SpreadsheetService();
        $spreadsheets = $service->getSpreadsheets();
        $spreadsheet = $spreadsheets->getByTitle($title);
        try {
            $worksheets = $spreadsheet->getWorksheets();
            foreach ($worksheets as $worksheet) {
                $object = new stdClass();
                $object->id = $worksheet->getId();
                $object->title = $worksheet->getTitle();
                $worksheetsArray[$object->id] = $object;
            }
        } catch (Exception $e) {
            
        }

        return $worksheetsArray;
    }

    public function getWorkSheetsColumns($accessToken, $title, $wTitle)
    {
        $columnsArray = array();
        $this->client->setAccessToken($accessToken);
        $token = json_decode($accessToken);
        if ($this->client->isAccessTokenExpired()) {
            $this->client->refreshToken($token->refresh_token);
            $token = json_decode($this->client->getAccessToken());
        }
        $request = new DefaultServiceRequest($token->access_token, $token->token_type);
        ServiceRequestFactory::setInstance($request);
        $service = new Google\Spreadsheet\SpreadsheetService();
        $spreadsheets = $service->getSpreadsheets();
        $spreadsheet = $spreadsheets->getByTitle($title);
        try {
            $worksheets = $spreadsheet->getWorksheets();
            $worksheet = $worksheets->getByTitle($wTitle);
            $columns = $worksheet->getCellFeed();
            foreach($columns->getEntries() as $entry) {
                $row = $entry->getRow();
                $col = $entry->getColumn();
                if($row > 1){
                    break;
                }
                $columnsArray[] = $columns->getCell($row, $col)->getContent();
            }
        } catch (Exception $e) {
            
        }

        return $columnsArray;
    }

    public function insert($accessToken, $row, $spreadsheetId, $worksheetId)
    {
        $sheetsArray = array();
        $worksheetsArray = array();
        try {
            $this->client->setAccessToken($accessToken);
            $token = json_decode($accessToken);
            if ($this->client->isAccessTokenExpired()) {
                $this->client->refreshToken($token->refresh_token);
                $token = json_decode($this->client->getAccessToken());
            }

            $request = new DefaultServiceRequest($token->access_token, $token->token_type);
            ServiceRequestFactory::setInstance($request);
            $service = new Google\Spreadsheet\SpreadsheetService();
            $spreadsheets = $service->getSpreadsheets();
            foreach($spreadsheets As $sheet){
                $sheetsArray[$sheet->getId()] = $sheet->getTitle();
            }
            $spreadsheet = $spreadsheets->getByTitle($sheetsArray[$spreadsheetId]);
            $worksheets = $spreadsheet->getWorksheets();
            foreach ($worksheets as $sheet){
                $worksheetsArray[$sheet->getId()] = $sheet->getTitle();
            }
            $worksheet = $worksheets->getByTitle($worksheetsArray[$worksheetId]);
            $list = $worksheet->getListFeed();
            $list->insert($row);
        } catch (Exception $e) {

        }
    }
}