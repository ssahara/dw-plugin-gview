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
 * SYNTAX: {{gview> url}} 
 *         {{gview> url height}}
 *         {{gview> url width,height}}
 *         {{gview> url width,height noreference}}
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
        //$this->Lexer->addSpecialPattern('{{gview>.*?}}',$mode,'plugin_gview');
        $this->Lexer->addSpecialPattern('{{gview>{{[^}\n]+}}[^}\n]*}}',$mode,'plugin_gview');
        $this->Lexer->addSpecialPattern('{{gview>[^}\n]+}}',$mode,'plugin_gview');
    }

 /**
  * handle syntax
  */
    public function handle($match, $state, $pos, &$handler){
        //global $conf;

        $match = trim(substr($match,8,-2));  // strip markup
        $opts = array( // set default
                     'url'  => '',
                     'title'  => $this->getLang('gview_linktext'),
                     'width'   => '100%',
                     'height'  => '300px',
                     'embedded' => true,
                     'reference'  => true,
                     'border'  => true,
                     );

        // get url for viewer
        if (preg_match('/(https?:\/\/[^ |} ]+)[ |}]/u', $match, $matches)) {
            $opts['url'] = $matches[1];
        }
        // get title (linktext)
        if (preg_match('/\|([^}]+)}/u', $match, $matches)) {
            $opts['title'] = $matches[1];
        } elseif (preg_match('/\|(\w+) /u', $match, $matches)) {
            $opts['title'] = $matches[1];
        }

        $tokens = preg_split('/\s+/', $match);
        foreach ($tokens as $token) {

            // get width and height of iframe
            $matches=array();
            if (preg_match('/(\d+(%|em|pt|px)?)\s*([,xX]\s*(\d+(%|em|pt|px)?))?/',$token,$matches)){
                if ($matches[4]) {
                    // width and height was given
                    $opts['width'] = $matches[1];
                    if (!$matches[2]) $opts['width'].= 'px'; //default to pixel when no unit was set
                    $opts['height'] = $matches[4];
                    if (!$matches[5]) $opts['width'].= 'px'; //default to pixel when no unit was set
                } elseif ($matches[2]) {
                    // only height was given
                    $opts['height'] = $matches[1];
                    if (!$matches[2]) $opts['height'].= 'px'; //default to pixel when no unit was set
                }
                continue;
            }
            // get reference option, ie. whether show original document url?
            if (preg_match('/noreference/',$token)){
                $opts['reference'] = false;
                continue;
            }
            // get embed option
            if (preg_match('/noembed(ded)?/',$token)){
                $opts['embedded'] = false;
                $opts['reference'] = false;
                continue;
            }
            // get border option
            if (preg_match('/no(frame)?border/',$token)){
              $opts['border'] = false;
              continue;
            }
        }
        return array($state, $opts);
    }

 /**
  * Render iframe or link for Google Docs Viewer Service
  */
    public function render($mode, &$renderer, $data) {
        global $conf;
        $viewerurl = 'http://docs.google.com/viewer';

        if ($mode != 'xhtml') return false;

        list($state, $opts) = $data;
        //if ( $opts['url'] =='') return false;
        $referencelink = '<a href="'.$opts['url'].'">'.$opts['url'].'</a>';

        $html.= '<div class="tpl_gview">'.NL;
        if ($opts['reference']) {
            $html.= sprintf($this->getLang('gview_reference_msg'), $referencelink);
            $html.= '<br />'.NL;
        }
        if ($opts['embedded']) {
            $html.= '<iframe src="'.$viewerurl;
            $html.= '?url='.urlencode( $opts['url']);
            $html.= '&embedded=true"';
            $html.= ' style="';
            if ($opts['width'])  { $html.= ' width: '.$opts['width'].';'; }
            if ($opts['height']) { $html.= ' height: '.$opts['height'].';'; }
            if ($opts['border'] == false) { $html.= ' border: none;'; }
            $html.= '"></iframe>'.NL;
        } else {
            $html.= '<a href="'.$viewerurl;
            $html.= '?url='.urlencode( $opts['url']);
            $html.= '">'.$opts['title'].'</a>'.NL;
        }
        $html.= '</div>'.NL;
        $renderer->doc.=$html;
        return true;
    }
}
