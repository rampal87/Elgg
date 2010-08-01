<?php
/**
 * Elgg Installer.
 * Controller for installing Elgg.
 *
 * @package Elgg
 * @subpackage Installer
 * @author Curverider Ltd
 * @link http://elgg.org/
 */

class ElggInstaller {

	protected $steps = array(
		'welcome',
		'requirements',
		'database',
		'settings',
		'admin',
		'complete',
		);

	protected $isAction;

	public function __construct() {
		$this->isAction = $_SERVER['REQUEST_METHOD'] === 'POST';

		$this->bootstrapConfig();
		
		$this->bootstrapEngine();

		elgg_set_viewtype('failsafe');

		set_error_handler('__elgg_php_error_handler');
		set_exception_handler('__elgg_php_exception_handler');
	}

	public function getSteps() {
		return $this->steps;
	}

	public function welcome() {
		$this->render('welcome');
	}

	public function requirements() {

		// attempt to create .htaccess file

		// check PHP parameters

		// check permissions on engine directory

		// check rewrite module

		$this->render('requirements', $params);
	}

	public function database() {
		if ($this->isAction) {
			$params = $this->validateDatabaseVars();

			$this->createSettingsFile($params);

			$this->bootstrapDatabaseSettings();

			$this->loadLibrary('database');

			$this->installDatabase($params);

			$this->continueToNextStep('database');
		}

		$variables = array('user', 'password', 'dbname', 'host', 'prefix');

		$params = array(
			'variables' => $variables,
		);

		$this->render('database', $params);
	}

	protected function validateDatabaseVars() {
		return $this->getPostVariables();
	}

	protected function createSettingsFile($params) {
		global $CONFIG;

		$templateFile = "{$CONFIG->path}engine/settings.example.php";
		$template = file_get_contents($templateFile);
		if (!$template) {
			// throw exception
		}

		foreach ($params as $k => $v) {
			$template = str_replace("{{".$k."}}", $v, $template);
		}

		$settingsFilename = "{$CONFIG->path}engine/settings.php";
		$result = file_put_contents($settingsFilename, $template);
		if (!$result) {
			// throw exception
		}
	}

	protected function installDatabase($params) {
		global $CONFIG;
		
		run_sql_script("{$CONFIG->path}engine/schema/mysql.sql");
	}

	public function settings() {
		if ($this->isAction) {
			// save system settings

			$this->continueToNextStep('settings');
		}

		$variables = array('sitename', 'siteemail', 'wwwroot', 'path', 'dataroot');

		$params = array(
			'variables' => $variables,
		);

		$this->render('settings', $params);
	}

	public function admin() {
		if ($this->isAction) {
			// create admin account

			$this->continueToNextStep('admin');
		}

		$variables = array('displayname', 'username', 'password1', 'password2', 'email');

		$params = array(
			'variables' => $variables,
		);
		$this->render('admin', $params);
	}

	public function complete() {
		$this->render('complete');
	}

	protected function render($step, $vars = array()) {
		
		$vars['next_step'] = $this->getNextStep($step);
		$title = elgg_echo("install:$step");
		$body = elgg_view("install/pages/$step", $vars);
		page_draw(
				$title,
				$body,
				'page_shells/install',
				array(
					'step' => $step,
					'steps' => $this->getSteps(),
					)
				);
		exit;
	}

	protected function bootstrapEngine() {
		global $CONFIG;

		$lib_dir = $CONFIG->path . 'engine/lib/';

		// bootstrapping with required files in a required order
		$required_files = array(
			'exceptions.php', 'elgglib.php', 'views.php', 'access.php', 'system_log.php', 'export.php',
			'sessions.php', 'languages.php', 'input.php', 'install.php', 'cache.php', 'output.php'
		);

		foreach ($required_files as $file) {
			$path = $lib_dir . $file;
			if (!include($path)) {
				echo "Could not load file '$path'. "
					. 'Please check your Elgg installation for all required files.';
				exit;
			}
		}
	}

	protected function bootstrapConfig() {
		global $CONFIG;
		if (!isset($CONFIG)) {
			$CONFIG = new stdClass;
		}

		$CONFIG->wwwroot = $this->getBaseUrl();
		$CONFIG->url = $CONFIG->wwwroot;
		$CONFIG->path = dirname(dirname(__FILE__)) . '/';
	}

	protected function getBaseUrl() {
		$protocol = 'http';
		if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
			$protocol = 'https';
		}
		$port = ':' . $_SERVER["SERVER_PORT"];
		if ($port == ':80' || $port == ':443') {
			$port = '';
		}
		$uri = $_SERVER['REQUEST_URI'];
		$cutoff = strpos($uri, 'install.php');
		$uri = substr($uri, 0, $cutoff);

		$url = "$protocol://{$_SERVER['SERVER_NAME']}$port{$uri}";
		return $url;
	}

	protected function getPostVariables() {
		$vars = array();
		foreach ($_POST as $k => $v) {
			$vars[$k] = $v;
		}
		return $vars;
	}

	protected function continueToNextStep($currentStep) {
		$this->isAction = FALSE;
		forward($this->getNextStepUrl($currentStep));
	}

	protected function getNextStep($currentStep) {
		return $this->steps[1 + array_search($currentStep, $this->steps)];
	}

	protected function getNextStepUrl($currentStep) {
		global $CONFIG;
		$nextStep = $this->getNextStep($currentStep);
		return "{$CONFIG->wwwroot}install.php?step=$nextStep";
	}
}
