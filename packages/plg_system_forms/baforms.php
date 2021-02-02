<?php
/**
* @package   BaForms
* @author    Balbooa http://www.balbooa.com/
* @copyright Copyright @ Balbooa
* @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
*/

defined('_JEXEC') or die;

jimport( 'joomla.plugin.plugin' );
jimport('joomla.filesystem.folder');
 
class plgSystemBaforms extends JPlugin
{
    public function __construct( &$subject, $config )
    {
        parent::__construct( $subject, $config );
    }

    function onAfterInitialise()
    {
        $app = JFactory::getApplication();
        if ($app->isSite()) {
            $path = JPATH_ROOT.'/components/com_baforms/helpers/baforms.php';
            JLoader::register('baformsHelper', $path);
        }
    }
    
    function onAfterRender()
    {
        $app = JFactory::getApplication();
        $a_id = $app->input->get('a_id');
        $doc = JFactory::getDocument();
        $option = $app->input->get('option', '', 'string');
        if ($app->isSite() && empty($a_id) && $doc->getType() == 'html' && $option != 'com_config') {
            $loaded = JLoader::getClassList();
            if (isset($loaded['baformshelper'])) {
                baformshelper::prepareHelper();
                $html = $app->getBody();
                $pos = strpos($html, '</head>');
                $head = substr($html, 0, $pos);
                $body = substr($html, $pos);
                include JPATH_ROOT.'/components/com_baforms/views/form/tmpl/click-trigger.min.php';
                $body = str_replace('</body>', $out.'</body>', $body);
                $content = $this->getContent($body);
                $html = $head.$content;
                $app->setBody($html);
            }
        }
    }
    
    function getContent($body)
    {
        if (empty(baformshelper::$about)) {
            baformshelper::prepareHelper();
        }
        $body = baformsHelper::renderFormHTML($body);

        return $body;
    }
}