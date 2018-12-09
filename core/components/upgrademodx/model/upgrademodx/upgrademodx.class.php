<?php

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;


//use GuzzleHttp\Exception\GuzzleException;
// use GuzzleHttp\Client;
/**
 * UpgradeMODX class file for UpgradeMODX Widget snippet for  extra
 *
 * Copyright 2015-2018 Bob Ray <https://bobsguides.com>
 * Created on 08-16-2015
 *
 * UpgradeMODX is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * UpgradeMODX is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * UpgradeMODX; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package upgrademodx
 */

/**
 * Description
 * -----------
 * UpgradeMODX Dashboard widget
 * This package was inspired by the work of a number of people and I have borrowed some of their code.
 * Dmytro Lukianenko (dmi3yy) is the original author of the MODX install script. Susan Sottwell, Sharapov,
 * Bumkaka, Inreti, Zaigham Rana, frischnetz, and AgelxNash, also contributed and I'd like to thank all
 * of them for laying the groundwork.
 *
 * Variables
 * ---------
 * @var $modx modX
 * @var $scriptProperties array
 *
 * @package upgrademodx
 **/

/* Properties

 * @property &groups textfield -- group, or commma-separated list of groups, who will see the widget; Default: (empty)..
 * @property &hideWhenNoUpgrade combo-boolean -- Hide widget when no upgrade is available; Default: No.
 * @property &interval textfield -- Interval between checks -- Examples: 1 week, 3 days, 6 hours; Default: 1 week.
 * @property &language textfield -- Two-letter code of language to user; Default: en.
 * @property &lastCheck textfield -- Date and time of last check -- set automatically; Default: (empty)..
 * @property &latestVersion textfield -- Latest version (at last check) -- set automatically; Default: (empty)..
 * @property &plOnly combo-boolean -- Show only pl (stable) versions; Default: yes.
 * @property &versionsToShow textfield -- Number of versions to show in upgrade form (not widget); Default: 5.

 */



if (!class_exists('UpgradeMODX')) {
    class UpgradeMODX {

        /** @var $versionArray array - array of versions to display if upgrade is available as a string
         *  to inject into upgrade script */
        public $versionArray = '';

        /** @var $renderedVersionList string - Rendered version list to use in createVersionForm */
        public $renderedVersionList = '';

        /** @var $versionListPath string - location of versionlist file */
        public $versionListPath;

        /** @var $modx modX - modx object */
        public $modx = null;

        /** @var $latestVersion string - latest version available */
        public $latestVersion = '';

        /** @var $errors array - array of error message (non-fatal errors only) */
        public $errors = array();

        /** @var $forcePclZip boolean */
        public $forcePclZip = false;

        /** @var $githubTimeout int */
        public $gitHubTimeout = 6;

        /** @var $modxTimeout int */
        public $modxTimeout = 6;

        /** @var $verifyPeer int */
        public $verifyPeer = true;

        /** @var $github_username string */
            public $github_username = '';

        /** @var $github_token string */
            public $github_token = '';

        /** @var $devMode bool */
            protected $devMode = false;

        /** @var $versionsToShow bool */
        protected $versionsToShow = 5;

        /** @var $progressFilePath string */
            protected $progressFilePath = '';

        /** @var $progressFileURL string */
        protected $progressFileURL = '';

        /** @var $client GuzzleHttp\Client */
        protected $client = null;

        /** @var $corePath string   */
        public $corePath = '';

        /** @var $plOnly bool */
        protected $plOnly = false;

        /** @var $certPath string */
        protected $certPath = '';

        /** @var $githubUrl string */
        protected $githubUrl = '';

        /** @var $verbose bool */
        protected $verbose = false;
        public function __construct($modx) {
            /** @var $modx modX */
            $this->modx = $modx;
        }

        public function init($props) {
            /** @var $InstallData array */
            $this->devMode = (bool)$this->modx->getOption('ugm.devMode', null, false, true);
            $this->verbose = (bool) $this->modx->getOption('ugm_verbose', null, false, true);
            $language = $this->modx->getOption('ugm_language',
                null, $this->modx->getOption('manager_language'), true);
            $language = empty($language) ? 'en': $language;
            $this->modx->lexicon->load($language . ':upgrademodx:default');
            $this->forcePclZip = $this->modx->getOption('ugm_force_pcl_zip', null, false, true);
            $this->plOnly = $this->modx->getOption('ugm_pl_only', null, true, true);
            $this->gitHubTimeout = $this->modx->getOption('ugm_github_timeout', null, 6, true);
            $this->modxTimeout = $this->modx->getOption('ugm_modx_timeout', null, 6, true);
            $this->githubUrl = $this->modx->getOption('ugm_versionlist_api_url',
                null, '//api.github.com/repos/modxcms/revolution/tags', true);;
            $this->errors = array();
            $this->latestVersion = $this->modx->getOption('ugm_latestVersion', null, '', true);
            $this->verifyPeer = (bool) $this->modx->getOption('ugm_ssl_verify_peer', null, true);
            $this->certPath = $this->modx->getOption('ugm_cert_path', null, '', true);
            $this->versionListPath = $this->getVersionListPath($this->modx->getOption('ugm_version_list_path', null),
                $this->devMode);
            $this->progressFilePath = MODX_ASSETS_PATH . 'components/upgrademodx/ugmprogress.txt';
            $this->mmkDir(MODX_ASSETS_PATH . 'components/upgrademodx');
            $this->progressFileURL = MODX_ASSETS_URL . 'components/upgrademodx/ugmprogress.txt';
            file_put_contents($this->progressFilePath, 'Starting Upgrade');
            $this->versionsToShow = $this->modx->getOption('ugm_versions_to_show', null, 5, true);
            $this->corePath = $this->modx->getOption('ugm.core_path', null,
                $this->modx->getOption('core_path') . 'components/upgrademodx/');
            require_once $this->corePath . 'vendor/autoload.php';
            /* These use System Setting if property is empty */
            $this->github_username = $this->modx->getOption('ugm_github_username', null, null, true);
            $this->github_token = $this->modx->getOption('ugm_github_token', null, null, true);
            $this->setGithubCredentials();
            $this->client = new \GuzzleHttp\Client();
            $this->mmkDir($this->versionListPath);
        }

        public function setGithubCredentials() {
            $username = $this->modx->getOption('ugm_github_username', null, null, true);
            if (empty($username)) {
                $x = 1;
            }
            $this->github_username = $username;
        }

        public function getVersionListPath($path, $devMode = false) {

            if ($devMode) {
                return('c:/dummy/ugmtemp/');
            }
            /* If path is empty or contains hard-coded default cache path,
               get true path from cacheManager */
            if ( ($path === '') || (stripos($path, 'core/cache/upgrademodx/') !== false)) {
                $cm = $this->modx->getCacheManager();
                $path = $cm->getCachePath() . 'upgrademodx/';
            }
            $this->mmkDir($path);
            return $path;
        }

        public function versionListExists() {
            return file_exists($this->versionListPath . 'versionlist');
        }

        public function createVersionForm($modx) {
            if (empty($this->renderedVersionList)) {
                // $this->modx->log(modX::LOG_LEVEL_ERROR, 'Getting Versionlist from file');
                $path = $this->versionListPath . 'versionlist';
                if (! file_exists($path)) {
                    $this->setError($this->modx->lexicon('ugm_no_version_list') . ' @ ' . $path);
                    return false;
                }
                $this->renderedVersionList = file_get_contents($path);
            }
            /** @var $upgrade  UpgradeMODX */
            /** @var $modx modX */
            $output = '';
            $output .= "\n" . '<div id="upgrade_form">';
            $output .= "\n<p>" . $modx->lexicon('ugm_get_major_versions') . '</p>';
            $output .= "\n" . '</div>' . "\n ";
            $output .= $this->renderedVersionList;
            if (stripos($output, 'Error') === false) {
                $output .= "\n" . $this->getButtonCode($modx->lexicon('ugm_begin_upgrade'));
            }
            return $output;
        }

        public static function getIeVersion() {
            $version = false;
            preg_match('/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'], $matches);
            if (count($matches) < 2) {
                preg_match('/Trident\/\d{1,2}.\d{1,2}; rv:([0-9]*)/', $_SERVER['HTTP_USER_AGENT'], $matches);
            }

            if (count($matches) > 1) {
                // We're using IE < version 12 (Edge)
                $version = $matches[1];
            }

            return $version;
        }

        public function getButtonCode($action = "[[+ugm_begin_upgrade]]", $disabled = false, $submitted = false) {
            $disabled = $disabled ? ' disabled ' : '';
            $red = $submitted ? ' red' : '';
            $ie = $this->getIeVersion();
            $buttonCode = '';

            if ($ie) {
                $buttonCode .= '
        <button type="submit" name="IeSucks" id="ugm_submit_button" class="progress-button' . $red . '" data-style="fill"
                data-horizontal' . $disabled . '>' . $action . '</button>';
            } else {
                $buttonCode = '
        <button type = "submit" name="IeSucks" id="ugm_submit_button" class="progress-button' . $red . '" data-style="rotate-angle-bottom" data-perspective
                data-horizontal' . $disabled . '>' .  $action . '</button>';
            }

            return $buttonCode;
        }

        /* Final arg is there for unit tests */
        public function finalizeVersionArray($contents, $plOnly = true, $versionsToShow = 5, $currentVersion = '') {
            $currentVersion = empty($currentVersion)
                ? $this->modx->getOption('settings_version', null)
                : $currentVersion;
            $contents = utf8_encode($contents);
            $contents = $this->modx->fromJSON($contents);
            if (empty($contents)) {
                $this->setError($this->modx->lexicon('ugm_json_decode_failed'));
                return false;
            }


             /* remove non-pl version objects if plOnly is set, and remove MODX 2.5.3 */
            foreach ($contents as $key => $content) {
                $name = substr($content['name'], 1);
                if ($plOnly && strpos($name, 'pl') === false) {
                    unset($contents[$key]);
                    continue;
                }
                if (strpos($name, '2.5.3-pl') !== false) {
                    unset($contents[$key]);
                }
            }
            $contents = array_values($contents); // 'reindex' array


            /* GitHub won't necessarily have them in the correct order.
               Sort them with a Custom insertion sort since they will
               be almost sorted already */

            /* Make sure we don't access an invalid index */
            $versionsToShow = min($versionsToShow, count($contents));

            /* Make sure we show at least one */
            $versionsToShow = !empty($versionsToShow) ? $versionsToShow : 1;

            /* Sort by version */
            $count = count($contents);
            for ($i = 0; $i < $count; $i++) {
                $element = $contents[$i];
                $j = $i;
                while ($j > 0 && (version_compare($contents[$j - 1]['name'], $element['name']) < 0)) {
                    $contents[$j] = $contents[$j - 1];
                    $j = $j - 1;
                }
                $contents[$j] = $element;
            }

            /* Truncate to $versionsToShow but extend to show current version
               plus one previous version */

            $versionArray = array();
            $i = 1;
            $currentFound = false;
            foreach ($contents as $version) {
                $name = substr($version['name'], 1);
                $compare = version_compare($currentVersion, $name);

                $shortVersion = strtok($name, '-');
                $url = 'https://modx.s3.amazonaws.com/releases/' . $shortVersion . '/modx-' . $name . '.zip';
                // $url = 'https://modx.com/download/direct?id=modx-' . $name . '.zip'; // backup if AWS not used
                $versionArray[$name] = array(
                    'tree' => 'Revolution',
                    'name' => 'MODX Revolution ' . htmlentities($name),
                    'link' => $url,
                    'location' => 'setup/index.php',
                    'selected' => false,
                    'current' => $compare === 0 ? true : false,
                );

                if ($currentFound && ($i >= ($versionsToShow))) {
                    break;
                }

                if ($compare >= 0) {
                    $currentFound = true;
                    $i++;
                    continue;
                }
                $i++;
            }

            /* Select oldest X.X.0 version newer than current version or
              latest if there isn't one. */
            reset($versionArray);
            $latest = key($versionArray);

            /* Reverse array so we can stop at the first one that
               fits the criteria */
            $versionArray = array_reverse($versionArray, true);
            $selectedOne = false;
            foreach ($versionArray as $key => $value) {

                $pattern = "/\d+\.\d+\.0/";
                /* If it's a .0 version newer than the current version, select it */
                if (preg_match($pattern, $key)) {
                    if (version_compare($key, $currentVersion) > 0) {
                        $versionArray[$key]['selected'] = true;
                        $selectedOne = true;
                        break;
                    }
                }
            }

            /* No .0 version - select latest version */
            if (!$selectedOne) {
                $versionArray[$latest]['selected'] = true;
            }

            /* Un-reverse it */
            $this->versionArray = array_reverse($versionArray, true);
            return $this->versionArray;
        }

        public function updateLatestVersion($versionArray) {
            reset($versionArray);
            $version = key($versionArray);
            // $this->modx->log(modX::LOG_LEVEL_ERROR, "LATEST: ".  print_r($version, true));
            $this->latestVersion = $version;
        }

        public function updateSettings($lastCheck, $latestVersion, $settingsVersion ) {
            $settings = array(
               'ugm_last_check' => strftime('%Y-%m-%d %H:%M:%S', $lastCheck),
               'ugm_latest_version' => $latestVersion,
               'ugm_file_version' => $settingsVersion,
            );
            $dirty = false;
            foreach($settings as $key => $value) {
                $setting = $this->modx->getObject('modSystemSetting', array('key' => $key));
                $success = true;
                if ($setting && $setting->get('value') !== $value) {
                    $dirty = true;
                    $setting->set('value', $value);
                    if (!$setting->save()) {
                        $success = false;
                    }
                } else {
                    if (! $setting) {
                        $success = false;
                    }
                }
                if ($dirty) {
                    $modxVersion = $this->modx->getOption('settings_version', true);
                    $cm = $this->modx->getCacheManager();
                    if (version_compare($modxVersion, '2.1.0-pl') >= 0) {
                        $cacheRefreshOptions = array('system_settings' => array());
                        $cm->refresh($cacheRefreshOptions);
                    }
                }

                if (!$success) {
                    $msg = '[UpdateMODX.class.php] ' .
                        $this->modx->lexicon('Could not update System Setting: ' . $key);
                    $this->setError($msg);
                }
            }
        }

        /**
         * @param $lastCheck string = time of previous check
         * @param $interval - interval between checks
         * @return bool true if time to check, false if not
         */
        public function timeToCheck($lastCheck, $interval = '+1 day') {
            if ($this->devMode) {
                return true;
            }
            if (empty($lastCheck)) {
                $retVal = true;
            } else {
                $interval = strpos($interval, '+') === false ? '+' . $interval : $interval;
                $retVal = time() > strtotime($lastCheck . ' ' . $interval);
            }
            return $retVal;
        }

        public function getVersionArray() {
            return $this->versionArray;
        }

        /**
         * @return bool|string
         * @throws GuzzleException
         */
        public function createVersionList() {
            $output = '';
            $versions = $this->getVersions($this->githubUrl, $this->gitHubTimeout,
                $this->verifyPeer, $this->github_username, $this->github_token, $this->certPath);
            // $this->modx->log(modX::LOG_LEVEL_ERROR, "USERNAME: " . $this->github_username . ' -- ' . "TOKEN: " . $this->github_token);
            if ($versions === false) {
                $output = false;
            } else {
                $versions = $this->finalizeVersionArray($versions, $this->plOnly, $this->versionsToShow);
                $this->versionArray = $versions;
                $itemGrid = array();
                foreach ($versions as $ver => $item) {
                    $itemGrid[$item['tree']][$ver] = $item;
                }
                $i = 0;
                $header = $this->modx->lexicon('ugm_choose_version');
                foreach ($itemGrid as $tree => $item) {
                    $output .= "\n" . '<div class="column">';

                    $output .= "\n" . '<label class="ugm_version_header"><span>' . $header . '</span></label>';

                    foreach ($item as $version => $itemInfo) {
                        $selected = $itemInfo['selected'] ? ' checked' : '';
                        $current = $itemInfo['current'] ? ' &nbsp;&nbsp;(' . '[[%ugm_current_version_indicator]]' . ')' : '';
                        $i = 0;
                        $output .= <<<EOD
                    \n<label><input type="radio"{$selected} name="modx" value="$version">
                    <span>{$itemInfo['name']} $current</span>
                    </label>
EOD;
                        $i++;
                    } // end inner foreach loop
                } // end outer foreach loop
                $output .= "\n</div>";
            }
            return $output;
        }


        /**
         * @return mixed returns JSON version list as string or false on failure
         * @throws GuzzleException
         * Gets raw JSON version list from GitHub
         */
        public function getVersions($url, $githubTimeout, $verifyPeer, $githubUsername = null,
                $githubToken = null, $certPath = null) {
            // $this->modx->log(modX::LOG_LEVEL_ERROR, 'Getting Version list from GitHub');
            $options = array();
            if ((!empty($githubUsername)) && (!empty($githubToken))) { // use token if set
                $options['auth'] = array($githubUsername, $githubToken);
            }
            $options['header'] = array (
                'Cache-Control' => 'no-cache',
               //  'Access-Control-Allow-Headers' => 'Authorization,Content-Type',
                'Accept' => 'application/json',
            );
            if (!empty ($certPath))  {
                $options['cert'] = $this->certPath;
            }

            $options['timeout'] = $githubTimeout;

            if ($verifyPeer !== true) {
                $options['verify'] = false;
            }

            try {
                $response = $this->client->request('GET', $url, $options);
                $retVal = $response->getBody();
                /* Simulate SSL error */

                //  } catch (\Exception $e) {
            } catch (RequestException $e) {
                $msg = $this->parseException($e, $this->verbose);
                $retVal = false;
                /* $this->setError($msg);
                 $req = Psr7\str($e->getRequest());
                 $x = $e->getMessage();
                 $code = $e->getCode();
                 $msg = json_decode($x);

                 if ($e->hasResponse()) {
                     $exception = (string)$e->getResponse()->getBody();
                     $exception = json_decode($exception);
                     $resp =  Psr7\str($e->getResponse());
                     $rArray = print_r($resp, true);

                     $message = $e->getResponse()->getReasonPhrase();


                 }

                 $msg = $this->modx->lexicon('ugm_no_version_list_from_github') . " &mdash; " . $e->getMessage(); */

            } catch (\Exception $e) {
                /** @var $e \Exception */
                $msg = $this->parseException($e);
                $retVal = false;
            }




            return $retVal;
        }

        /**
         * @param $e GuzzleHttp\Exception\RequestException
         * @return string - Error message based on Exception
         *
         */
        public function parseException($e, $verbose = false) {
            $msg = $e->getMessage();
            $prefix = $this->modx->lexicon('ugm_no_version_list_from_github') . ' -- ';
            $retVal = $msg; // default to entire message;
            $code = $e->getCode();

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $exception = (string)$e->getResponse()->getBody();
                $message = json_decode($exception);
                // $x = print_r($message, true);
                if (empty($message)) {
                    $message = $response->getReasonPhrase();
                    $retVal = $code . ' ' . $message;
                } else {
                    $ex = (array) $message;
                    $retVal = $code . ' ' . $ex['message'];
                }
            } elseif (empty($code) || ($code >= 500)) {
                $code = ((int) $code === 0) ? '503' : $code;
                $retVal = $code . ' ' . 'Connection error (no internet?)';
            }
            $retVal = $verbose? $prefix . ' ' . $msg : $prefix . $retVal;
            $this->setError($retVal);
            return $retVal;
        }

        public function clearErrors() {
            $this->errors = array();
        }

        public function getLatestVersion() {
            return $this->latestVersion;
        }

        public function setError($msg) {
            $this->errors[] = $msg;
        }

        public function getErrors() {
            return $this->errors;
        }


        /**
         * @param $settingsVersion
         * @return bool
         * @throws GuzzleException
         */
        public function upgradeAvailable($settingsVersion) {
            $this->renderedVersionList = $this->createVersionList();
            if (! empty($this->renderedVersionList)) {

                $versions = $this->versionArray;

                if (!empty($versions)) {
                    /* Set $this->latestVersion */
                    $this->updateLatestVersion($versions);

                    /* Update settings */
                    $this->updateSettings(time(), $this->latestVersion, $settingsVersion);

                    /* Update versionlist file  */
                    $this->updateVersionListFile($this->renderedVersionList);
                } else {
                    $this->setError('Versions Empty');
                }
            }

            $latestVersion = $this->latestVersion;

            if (!empty($this->errors)) {
                $upgradeAvailable = false;
            } else {
                /* See if the latest version is newer than the current version */
                $newVersion = version_compare($settingsVersion, $latestVersion) < 0;
                $upgradeAvailable = $newVersion;
            }
            return $upgradeAvailable;
        }

        public function updateVersionListFile($renderedVersionList) {
            $path = $this->versionListPath;
           // $this->modx->log(modX::LOG_LEVEL_ERROR, 'PATH: ' . $path);
            $this->mmkDir($path);

            $fp = @fopen($this->versionListPath . 'versionlist', 'w');
            if ($fp) {
                fwrite($fp, $renderedVersionList);
                fclose($fp);
            } else {
                $this->setError($this->modx->lexicon('ugm_could_not_open') .
                    ' ' . $path . 'versionlist ' . ' ' .
                    $this->modx->lexicon('ugm_for_writing'));
            }
        }

        public function mmkDir($folder, $perm = 0755) {
            if (!is_dir($folder)) {
                mkdir($folder, $perm, true);
            }
        }
    }
}
