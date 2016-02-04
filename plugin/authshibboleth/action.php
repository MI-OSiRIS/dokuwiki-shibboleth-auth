<?php

/**
 * DokuWiki Plugin authshibboleth (Action Component)
 *
 * Intercepts the 'login' action and redirects the user to the Shibboleth Session Initiator Handler
 * instead of showing the login form.
 * 
 * @author  Ivan Novakov http://novakov.cz/
 * @license http://debug.cz/license/bsd-3-clause BSD 3 Clause 
 * @link https://github.com/ivan-novakov/dokuwiki-shibboleth-auth
 */

// must be run within Dokuwiki
if (! defined('DOKU_INC'))
    die();

if (! defined('DOKU_LF'))
    define('DOKU_LF', "\n");
if (! defined('DOKU_TAB'))
    define('DOKU_TAB', "\t");
if (! defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once DOKU_PLUGIN . 'action.php';


class action_plugin_authshibboleth extends DokuWiki_Action_Plugin
{

    const CONF_SHIBBOLETH_HANDLER_BASE = 'shibboleth_handler_base';

    const CONF_LOGIN_HANDLER = 'login_handler';

    const CONF_LOGIN_TARGET = 'login_target';

    const CONF_LOGIN_HANDLER_LOCATION = 'login_handler_location';

    const CONF_LOGIN_DS = 'login_discovery_service';

    const CONF_LOGIN_PLAIN = 'login_plain_auth';


    public function register(Doku_Event_Handler &$controller)
    {
    	if ($this->getConf(self::CONF_LOGIN_DS) === true) {
    		$controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, '_hookcss');
    	} else {
    	    $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'redirectToLoginHandler');
    	}

        $controller->register_hook('HTML_LOGINFORM_OUTPUT', 'BEFORE', $this, 'handleLoginForm');
    }


    public function _hookcss(Doku_Event $event, $param) {
        // Adding a stylesheet
        $event->data["link"][] = array (
        "type" => "text/css",
        "rel" => "stylesheet",
        "href" =>  DOKU_BASE . "lib/plugins/authshibboleth/idpselect.css"
        );
    }

    // TODO:  Enable this to support display of login form and plaintext local auth if configured

    public function redirectToLoginHandler($event, $param)
    {
        global $ACT;
        
        if ('login' == $ACT) { 
            $loginHandlerLocation = $this->generateLoginHandlerLink();
            header("Location: " . $loginHandlerLocation);
            exit();
        }
    }


    public function handleLoginForm(Doku_Event &$event, $param) {

        if ($this->getConf(self::CONF_LOGIN_DS) === true) {

            $target = $this->mkTargetUrl();
            $jsconfig = DOKU_BASE . 'lib/plugins/authshibboleth/idpselect_config.js';
            $jsmain = DOKU_BASE . 'lib/plugins/authshibboleth/idpselect.js';
            // The extra script at the end adds a hidden form element name=target value=referer url
            // I was trying to avoid modifying the default idp js that is packaged with shibboleth-embedded-ds

            $msg = '<div id="idpSelect"></div>' .
                '<script src="' . $jsconfig . '" type="text/javascript" language="javascript"></script>' .
                '<script src="' . $jsmain . '" type="text/javascript" language="javascript"></script>' .
                '<script type="text/javascript">
                idpEntry = document.getElementById("idpSelectIdPEntryTile");
                idpPrefTile = document.getElementById("idpSelectPreferredIdPTile")
        	    if (idpPrefTile) { 
        		idpPref = idpPrefTile.getElementsByTagName("a"); 
                    	for(var i=0; i<idpPref.length; i++) {
                        	ohref = idpPref[i].href;
        	                idpPref[i].href = ohref + "&target=' . $target . '";
        		}
                }
                idpEntry.firstElementChild.insertAdjacentHTML("beforeend","<input value=\"' . $target . '\" name=\"target\" type=\"hidden\">");
                </script>
                <noscript>
                Javascript is required to use Shibboleth login service
                </noscript>
                ';

               

            } else {
                $msg = '<p><a href="' . $this->generateLoginHandlerLink() . '">Log In with Shibboleth</a></p>';
            }
            
             // replace login form with my (non)form
            $form = new Doku_Form(array('id' => 'dw__login'));
            $form->addElement($msg);
            $event->data = $form;
            // so it would be nice to display the regular form and enable both plain and shib login but I'm leaving that for another day
            // displaying the form works fine but some changes are needed to do the plain auth on login
            //$event->data->addElement($msg);
    }

    protected function generateLoginHandlerLink() {
        $loginHandlerLocation = $this->getConf(self::CONF_LOGIN_HANDLER_LOCATION);
            if (! $loginHandlerLocation) {
                $loginTarget = $this->getConf(self::CONF_LOGIN_TARGET);
                if (! $loginTarget) {
                    $loginTarget = $this->mkTargetUrl();
                }
                
                $loginHandlerLocation = $this->mkUrl($_SERVER['HTTP_HOST'], $this->mkShibHandler(), array(
                    'target' => $loginTarget
                ));
            }
        return $loginHandlerLocation;
    }

    protected function mkTargetUrl() {
        $pageURL = 'http';
        if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"];
            // . $_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"];
            // . $_SERVER["REQUEST_URI"];
        }

        $urlParts = parse_url($_SERVER["REQUEST_URI"]);
        parse_str($urlParts['query'], $query);
        $qs = $this->mkQueryString($query);
        return $pageURL . $urlParts['path'] . $qs;
    }


    protected function mkShibHandler()
    {
        return sprintf("%s%s", $this->getConf(self::CONF_SHIBBOLETH_HANDLER_BASE), $this->getConf(self::CONF_LOGIN_HANDLER));
    }


    protected function mkUrl($host, $path, $params = array(), $ssl = true)
    {
        return sprintf("%s://%s%s%s", $ssl ? 'https' : 'http', $host, $path, $this->mkQueryString($params));
    }

    /* 
    protected function mkRefererUrl($ssl = true)
    {

	pageURL = 'http';
        if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        $pageURL .= "://";
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];

        $urlParts = parse_url($pageURL);
        
        $host = $urlParts['host'];
        if ($urlParts['port'] && $urlParts['port'] != '80' && $urlParts['port'] != '443') {
            $host .= ':' . $urlParts['port'];
        }
        
        $query = array();
        parse_str($urlParts['query'], $query);
        
        return $this->mkUrl($host, $urlParts['path'], $query, $ssl);
    }

   */

    protected function mkQueryString($params = array())
    {
        if (empty($params)) {
            return '';
        }
        
        $queryParams = array();
        foreach ($params as $key => $value) {
            $queryParams[] = sprintf("%s=%s", $key, urlencode($value));
        }
        
        return '?' . implode('amp;', $queryParams);
    }
}
