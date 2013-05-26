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
    public function getType()  { return 'substition'; }
    public function getPType() { return 'normal'; }
    public function getSort()  { return 305; }
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{gview.*?\>.*{{.+?}}[^\n]*}}',$mode,'plugin_gview');
        $this->Lexer->addSpecialPattern('{{gview.*?\>.*?}}',$mode,'plugin_gview');

        // trick pattern :-)
        $this->Lexer->addSpecialPattern('{{obj:.*?\>.*{{.+?}}[^\n]*}}',$mode,'plugin_gview');
        $this->Lexer->addSpecialPattern('{{obj:.*?\>.*?}}',$mode,'plugin_gview');
    }

    /**
     * show syntax in preview mode
     */
    function _show_usage() {
        $syntax ='{{gview [size] [noembed] [noreference] > mediaID|title }}';
        msg('Gview plugin usage: '.$syntax,-1);
    }


    /**
     * handle syntax
     */
    public function handle($match, $state, $pos, &$handler){

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

        list($params, $media) = explode('>',$match,2);

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

        // handle media parameters (ID and title)
        $media = trim($media, ' {}');
        if ((strpos($media,' ') !== false) && ($opts['class'] =='gview')) {
            // likely wrong usage (older syntax used)
            $this->_show_usage();
        }

        if (strpos($media,'|') !== false) {
            list($media, $title) = explode('|',$media,2);
        }
        $opts['id'] = trim($media);
        if (!empty($title)) $opts['title'] = trim($title);

        return array($state, $opts);
    }

    /**
     * Render iframe or link for Google Docs Viewer Service
     */
    public function render($mode, &$renderer, $data) {

        if ($mode != 'xhtml') return false;

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
     * Create Media Link from DokuWiki media id
     * as to $conf['userewrite'] parameter.
     * @see function ml() in inc/common.php
     */
    function _suboptimal_ml($id ='') {
        global $conf;
        // external URLs are always direct without rewriting
        if(preg_match('#^(https?|ftp)://#i', $id)) {
            return $id;
        }
        if ($id === '') $id = $conf['start'];
        $id = idfilter($id);
        $xlink = DOKU_URL;
        if ($conf['userewrite'] == 1) {
            // rewrite module enabled in your web server
            $xlink .= $id;
        } else {
            // !!! EXPERIMENTAL : WEB SITE SPECIFIC FEATURE !!!
            // otherwise, assume "DOKU_URL/_media" directory 
            // which physically mapped or linked to 
            // your DW_DATA_PATH/media directory.
            // WebServer solution includes htpd.conf, IIS virtual directory.
            // Symbolic link or Junction are Filesystem solution.
            // Example:
            // if linux: ln -s DW_DATA_PATH/media _media
            // if iis6(Win2003S): linkd.exe _media DW_DATA_PATH/media
            // if iis7(Win2008S): mklink.exe /d _media DW_DATA_PATH/media
            //
            $xlink .= '_media';  // should be configurable in admin panel?
            $xlink .= str_replace(':','/',$id);
        }
        return $xlink;
    }

    /**
     * Generate html for sytax {{obj:>}}
     */
    function _html_embed($opts) {

        // make reference link
        $url = $this->_suboptimal_ml($opts['id']);
        $referencelink = '<a href="'.$url.'">'.urldecode($url).'</a>';

        if (empty($opts['class'])) {
            $html = '<div class="obj_container">'.NL;
        } else {
            $html = '<div class="obj_container_'.$opts['class'].'">'.NL;
        }
        if (!$opts['embedded']) {
            $html.= $referencelink;
        } else {
            if ($opts['reference']) {
                $html.= '<div class="obj_note">';
                $html.= sprintf($this->getLang('reference_msg'), $referencelink);
                $html.= '<div />'.NL;
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
    function _html_embed_gview($opts) {

        $viewerurl = 'http://docs.google.com/viewer';

        // make reference link
        $url = $this->_suboptimal_ml($opts['id']);
        $referencelink = '<a href="'.$url.'">'.urldecode($url).'</a>';

        $html = '<div class="obj_container_gview">'.NL;
        if (!$opts['embedded']) {
            $html.= $referencelink;
        } else {
            if ($opts['reference']) {
                $html.= '<div class="obj_note">';
                $html.= sprintf($this->getLang('reference_msg'), $referencelink);
                $html.= '<div />'.NL;
            }
            $html.= '<iframe src="'.$viewerurl;
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
