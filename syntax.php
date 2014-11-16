<?php
/**
 * DokuWiki Plugin Gview (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Sahara Satoshi <sahara.satoshi@gmail.com>
 *
 * @see also:https://docs.google.com/viewer#
 * Google Docs Viewer Terms of Service
 * By using this service you acknowledge that you have read and 
 * agreed to the Google Docs Viewer Terms of Service.
 *
 * Google Docs Viewer plugin
 * Shows a online document using Google Docs Viewer Service.
 * SYNTAX: {{gview [size] [noembed] [noreference] > mediaID|title }}
 *         {{obj:[class] [size] [noembed] [noreference] > mediaID|title }}
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';

class syntax_plugin_gview extends DokuWiki_Syntax_Plugin {

    // URL of Google Docs Viwer Service
    const URLgoogleViwer = 'https://docs.google.com/viewer';

    public function getType()  { return 'substition'; }
    public function getPType() { return 'normal'; }
    public function getSort()  { return 305; }
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{gview.*?>.*?}}',$mode,'plugin_gview');

        // trick pattern :-)
        $this->Lexer->addSpecialPattern('{{obj:.*?>.*?}}',$mode,'plugin_gview');
    }


    /**
     * Resolve media URLs
     * Create Media Link from DokuWiki media id considering $conf['userewrite'] value.
     * @see function ml() in inc/common.php
     *
     * @param (string) $linkId   mediaId
     * @return (string)          URL that does NOT contain DOKU_URL
     */
    private function _resolveMediaUrl($linkId = '') {
        global $ACT, $ID, $conf;

        // external URLs are always direct without rewriting
        if(preg_match('#^https?://#i', $linkId)) {  return $linkId; }

        resolve_mediaid(getNS($ID), $linkId, $exists);
        $linkId = idfilter($linkId);
        if (!$exists && ($ACT=='preview')) {
            msg($this->getPluginName().': media file not exists: '.$linkId, -1);
            return false;
        }
        // check access control
        if (!media_ispublic($linkId) && ($ACT=='preview')) {
            msg($this->getPluginName().': '.$linkId.' is NOT public!', 2);
        }
        // check MIME setting of DokuWiki - mime.conf/mime.local.conf
        // Embedding will fail if the media file is to be force_download.
        list($ext, $mtype, $force_download) = mimetype($linkId);
        if (!$force_download) {
            switch ($conf['userewrite']){
                case 0: // No URL rewriting
                    $mediapath = 'lib/exe/fetch.php?media='.$linkId;
                    break;
                case 1: // serverside rewiteing eg. .htaccess file
                    $mediapath = '_media/'.$linkId;
                    break;
                case 2: // DokuWiki rewiteing
                    $mediapath = 'lib/exe/fetch.php/'.$linkId;
                    break;
            }
        } else {
            // try alternative url to avoid download dialog.
            //
            // !!! EXPERIMENTAL : WEB SITE SPECIFIC FEATURE !!!
            // we assume "DOKU_URL/_media" directory 
            // which physically mapped or linked to 
            // your DW_DATA_PATH/media directory.
            // WebServer solution includes htpd.conf, IIS virtual directory.
            // Symbolic link or Junction are Filesystem solution.
            // Example:
            // if linux: ln -s DW_DATA_PATH/media _media
            // if iis6(Win2003S): linkd.exe _media DW_DATA_PATH/media
            // if iis7(Win2008S): mklink.exe /d _media DW_DATA_PATH/media
            //

            $altMediaBaseDir = $this->getConf('alternative_mediadir');
            if (empty($altMediaBaseDir)) $altMediaBaseDir ='/';
            if ($linkId[0] == ':') $linkId = substr($linkId, 1);
            $mediapath = $altMediaBaseDir . str_replace(':','/',$linkId);
            if ($ACT=='preview') {
                msg($this->getPluginName().': alternative url ('.$mediapath.') will be used for '.$linkId, 2);
            }
        }
        // $mediapath does not contain "http://" and hostname
        return $mediapath;
    }



    /**
     * handle syntax
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){

        $opts = array( // set default
                     'id'      => '',
                     'title'   => $this->getLang('gview_linktext'),
                     'class'   => '',
                     'width'   => '100%',
                     'height'  => '300px',
                     'embedded' => true,
                     'reference'  => true,
                     //'border'  => false,
                     );

        list($params, $media) = explode('>', trim($match,'{}'), 2);

        // handle media parameters (linkId and title)
        list($linkId, $title) = explode('|', $media, 2);

        // handle viewer parameters
        // split phrase of parameters by white space
        $tokens = preg_split('/\s+/', $params);
        
        // check frist markup
        $markup = array_shift($tokens); // first param
        if (strpos($markup,'gview') !== false) {
            $opts['class'] = 'gview';
        } elseif (strlen($markup) > 6) {
            $opts['class'] = substr($markup,6); // strip "{{obj:"
        }

        foreach ($tokens as $token) {

            // get width and height of iframe
            $matches=array();
            if (preg_match('/(\d+(%|em|pt|px)?)([,xX](\d+(%|em|pt|px)?))?/',$token,$matches)){
                if ($matches[4]) {
                    // width and height was given
                    $opts['width'] = $matches[1];
                    if (!$matches[2]) $opts['width'].= 'px'; //default to pixel when no unit was set
                    $opts['height'] = $matches[4];
                    if (!$matches[5]) $opts['height'].= 'px'; //default to pixel when no unit was set
                    continue;
                } elseif ($matches[2]) {
                    // only height was given
                    $opts['height'] = $matches[1];
                    if (!$matches[2]) $opts['height'].= 'px'; //default to pixel when no unit was set
                    continue;
                }
            }
            // get reference option, ie. whether show original document url?
            if (preg_match('/no(reference|link)/',$token)) {
                $opts['reference'] = false;
                continue;
            }
            // get embed option
            if (preg_match('/noembed(ded)?/',$token)) {
                $opts['embedded'] = false;
                continue;
            }
            // get border option
            if (preg_match('/no(frame)?border/',$token)) {
              $opts['border'] = false;
              continue;
            }
        }

        $opts['id'] = trim($linkId);
        if (!empty($title)) $opts['title'] = trim($title);

        return array($state, $opts);
    }

    /**
     * Render iframe or link for Google Docs Viewer Service
     */
    public function render($format, Doku_Renderer $renderer, $data) {

        if ($format != 'xhtml') return false;

        list($state, $opts) = $data;
        if ( $opts['id'] =='') return false;

        switch ($opts['class']) {
            case 'gview':
                $html = $this->_html_embed_gview($opts);
                break;
            default:
                $html = $this->_html_embed($opts);
                break;
        }
        $renderer->doc.=$html;
        return true;
    }

    /**
     * Generate html for sytax {{obj:>}}
     */
    private function _html_embed($opts) {

        // make reference link
        $url = $this->_resolveMediaUrl($opts['id']);
        $referencelink = '<a href="'.$url.'">'.urldecode($url).'</a>';

        if (empty($opts['class'])) {
            $html = '<div class="obj_container">'.NL;
        } else {
            $html = '<div class="obj_container_'.$opts['class'].'">'.NL;
        }
        if (!$opts['embedded']) {
            $html.= '<a href="'.$url.'">'.$opts['title'].'</a>';
        } else {
            if ($opts['reference']) {
                $html.= '<div class="obj_note">';
                $html.= sprintf($this->getLang('reference_msg'), $referencelink);
                $html.= '</div>'.NL;
            }
            $html.= '<object data="'.urldecode($url).'"';
            $html.= ' style="';
            if ($opts['width'])  { $html.= ' width: '.$opts['width'].';'; }
            if ($opts['height']) { $html.= ' height: '.$opts['height'].';'; }
            $html.= '">'.NL;
            $html.= '</object>'.NL;
        }
        $html.= '</div>'.NL;

        return $html;
    }

    /**
     * Generate html for sytax {{obj:gview>}} or {{gview>}}
     *
     * @see also: https://docs.google.com/viewer#
     */
    private function _html_embed_gview($opts) {

        // make reference link
        $url = DOKU_URL.$this->_resolveMediaUrl($opts['id']);
        $referencelink = '<a href="'.$url.'">'.urldecode($url).'</a>';

        $html = '<div class="obj_container_gview">'.NL;
        if (!$opts['embedded']) {
            $html.= '<a href="'.$url.'">'.$opts['title'].'</a>';
        } else {
            if ($opts['reference']) {
                $html.= '<div class="obj_note">';
                $html.= sprintf($this->getLang('reference_msg'), $referencelink);
                $html.= '</div>'.NL;
            }
            $html.= '<iframe src="'.self::URLgoogleViwer;
            $html.= '?url='.urlencode($url);
            $html.= '&embedded=true"';
            $html.= ' style="';
            if ($opts['width'])  { $html.= ' width: '.$opts['width'].';'; }
            if ($opts['height']) { $html.= ' height: '.$opts['height'].';'; }
            //if ($opts['border'] == false) { $html.= ' border: none;'; }
            $html.= ' border: none;';
            $html.= '"></iframe>'.NL;
        }
        $html.= '</div>'.NL;

        return $html;
    }

}
