<?php
/**
 * Installation Controller
 *
 * @category Controllers
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
namespace application\controllers\install;

use application\core\Controller;
use application\core\Database;
use application\helpers\StringsHelper;
use application\libraries\FunctionsLib as Functions;
use application\libraries\PlanetLib;

/**
 * Installation Class
 */
class Installation extends Controller
{
    private $host;
    private $name;
    private $user;
    private $password;
    private $prefix;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->_planet = new PlanetLib();

        // load Model
        parent::loadModel('install/installation');

        // load Language
        parent::loadLang('installation/installation');

        // build the page
        $this->buildPage();
    }

    /**
     * Build the page
     *
     * @return void
     */
    private function buildPage(): void
    {
        $parse = $this->langs->language;
        $continue = true;

        if (!$this->serverRequirementes()) {
            $alert = $this->saveMessage($this->langs->line('ins_no_server_requirements'), 'error');
            $continue = false;
        }

        // VERIFICATION - WE NEED THE config DIR WRITABLE
        if (!$this->isWritable()) {
            $alert = $this->saveMessage($this->langs->line('ins_not_writable'), 'error');
            $continue = false;
        }

        // VERIFICATION - WE DON'T WANT ANOTHER INSTALLATION
        if ($this->isInstalled()) {
            $alert = $this->saveMessage($this->langs->line('ins_already_installed'), 'error');
            $continue = false;
        }

        if (!$continue) {
            parent::$page->displayInstall(
                $this->getTemplate()->set(
                    'install/in_welcome_view',
                    array_merge(
                        ['alert' => $alert],
                        $this->langs->language
                    )
                ),
                $this->langs->language
            );
        }

        // ACTION FOR THE CURRENT PAGE
        $parse['alert'] = '';

        switch ((isset($_POST['page']) ? $_POST['page'] : '')) {
            case 'step1':
                $this->host = isset($_POST['host']) ? $_POST['host'] : null;
                $this->user = isset($_POST['user']) ? $_POST['user'] : null;
                $this->password = isset($_POST['password']) ? $_POST['password'] : null;
                $this->name = isset($_POST['db']) ? $_POST['db'] : null;
                $this->prefix = isset($_POST['prefix']) ? $_POST['prefix'] : null;

                if (!$this->validateDbData()) {
                    $alerts = $this->langs->line('ins_empty_fields_error');
                    $continue = false;
                }

                if ($continue && !$this->tryConnection()) {
                    $alerts = $this->langs->line('ins_not_connected_error');
                    $continue = false;
                }

                if ($continue && !$this->tryDatabase()) {
                    $alerts = $this->langs->line('ins_db_not_exists');
                    $continue = false;
                }

                if ($continue && !$this->writeConfigFile()) {
                    $alerts = $this->langs->line('ins_write_config_error');
                    $continue = false;
                }

                if ($continue) {
                    Functions::redirect('?page=installation&mode=step2');
                }

                $parse['alert'] = $this->saveMessage($alerts, 'warning');
                $parse['v_host'] = $this->host;
                $parse['v_db'] = $this->name;
                $parse['v_user'] = $this->user;
                $parse['v_prefix'] = $this->prefix;

                $current_page = $this->getTemplate()->set(
                    'install/in_database_view', $parse
                );

                break;

            case 'step2':
                if ($continue) {
                    Functions::redirect('?page=installation&mode=step3');
                }

                $parse['alert'] = $this->saveMessage($alerts, 'warning');
                $current_page = $this->getTemplate()->set(
                    'install/in_database_view',
                    $parse
                );

                break;

            case 'step3':
                if (!$this->insertDbData()) {
                    $alerts = $this->langs->line('ins_insert_tables_error');
                    $continue = false;
                }

                if ($continue) {
                    Functions::redirect('?page=installation&mode=step4');
                }

                $parse['alert'] = $this->saveMessage($alerts, 'warning');
                $current_page = $this->getTemplate()->set(
                    'install/in_database_view',
                    $parse
                );
                break;

            case 'step4':
                Functions::redirect('?page=installation&mode=step5');
                break;

            case 'step5':
                $create_account_status = $this->createAccount();

                if ($create_account_status < 0) {
                    // Failure
                    if ($create_account_status == -1) {
                        $error_message = $this->langs->line('ins_adm_empty_fields_error');
                    } else {
                        $error_message = $this->langs->line('ins_adm_invalid_email_address');
                    }

                    $parse['alert'] = $this->saveMessage($error_message, 'warning');

                    $current_page = $this->getTemplate()->set(
                        'install/in_create_admin_view',
                        $parse
                    );

                    $continue = false;
                }

                if ($continue) {
                    // set last stat update
                    Functions::updateConfig('stat_last_update', time());

                    // set the installation language to the game language
                    Functions::updateConfig('lang', Functions::getCurrentLanguage());

                    $current_page = $this->getTemplate()->set(
                        'install/in_create_admin_done_view',
                        array_merge($parse, $this->langs->language)
                    );

                    // This will continue on false meaning "This is the end of the installation, no else where to go"
                    $continue = false;
                }
                break;

            case '':
            default:
                break;
        }

        if ($continue) {
            switch ((isset($_GET['mode']) ? $_GET['mode'] : '')) {
                case 'step1':
                    $current_page = $this->getTemplate()->set(
                        'install/in_database_view',
                        array_merge(
                            [
                                'alert' => '',
                                'v_host' => '',
                                'v_user' => '',
                                'v_db' => '',
                                'v_prefix' => '',
                            ],
                            $this->langs->language
                        )
                    );

                    break;

                case 'step2':
                    $parse['step'] = 'step2';
                    $parse['done_config'] = '';
                    $parse['done_connected'] = $this->langs->line('ins_done_connected');
                    $parse['done_insert'] = '';
                    $current_page = $this->getTemplate()->set(
                        'install/in_done_actions_view',
                        $parse
                    );

                    break;

                case 'step3':
                    $parse['step'] = 'step3';
                    $parse['done_config'] = $this->langs->line('ins_done_config');
                    $parse['done_connected'] = '';
                    $parse['done_insert'] = '';
                    $current_page = $this->getTemplate()->set(
                        'install/in_done_actions_view',
                        $parse
                    );

                    break;

                case 'step4':
                    $parse['step'] = 'step4';
                    $parse['done_config'] = '';
                    $parse['done_connected'] = '';
                    $parse['done_insert'] = $this->langs->line('ins_done_insert');
                    $current_page = $this->getTemplate()->set(
                        'install/in_done_actions_view',
                        $parse
                    );

                    break;

                case 'step5':
                    $parse['step'] = 'step5';
                    $current_page = $this->getTemplate()->set(
                        'install/in_create_admin_view',
                        $parse
                    );

                    break;

                case 'license':
                    $current_page = $this->getTemplate()->set(
                        'install/in_license_view',
                        $this->langs->language
                    );

                    break;

                case '':
                case 'overview':
                default:
                    $current_page = $this->getTemplate()->set(
                        'install/in_welcome_view',
                        array_merge($parse, $this->langs->language)
                    );

                    break;
            }
        }

        parent::$page->displayInstall($current_page, $this->langs->language);
    }

    /**
     * method server_requirementes
     * param
     * return true if the required server requirements are met
     */
    private function serverRequirementes()
    {
        return !(version_compare(PHP_VERSION, '7.3.0', '<'));
    }

    /**
     * isWritable
     *
     * @return boolean
     */
    private function isWritable()
    {
        $config_dir = XGP_ROOT . 'application/config/';

        return is_writable($config_dir);
    }

    /**
     * isInstalled
     *
     * @return boolean
     */
    private function isInstalled()
    {
        // if file not exists
        $config_file = XGP_ROOT . 'application/config/config.php';

        if (!file_exists($config_file) or filesize($config_file) == 0) {
            return false;
        }

        // if no db object
        if (!defined('DB_NAME')) {
            return false;
        }

        // check if tables exist
        if (!$this->tablesExists()) {
            return false;
        }

        // check for admin account
        if (!$this->adminExists()) {
            return false;
        }

        return true;
    }

    /**
     * tablesExists
     *
     * @return boolean
     */
    private function tablesExists()
    {
        $result = $this->Installation_Model->getListOfTables(DB_NAME);
        $arr = [];

        foreach ($result as $row) {
            foreach ($row as $table) {
                if (strpos($table, DB_PREFIX) !== false) {
                    $arr[] = $table;
                }
            }
        }

        return (count($arr) > 0);
    }

    /**
     * adminExists
     *
     * @return boolean
     */
    private function adminExists()
    {
        return $this->Installation_Model->getAdmin()['count'] >= 1;
    }

    /**
     * tryConnection
     *
     * @return boolean
     */
    private function tryConnection()
    {
        return $this->Installation_Model->tryConnection($this->host, $this->user, $this->password);
    }

    /**
     * tryDatabase
     *
     * @return boolean
     */
    private function tryDatabase()
    {
        return $this->Installation_Model->tryDatabase($this->name);
    }

    /**
     * writeConfigFile
     *
     * @return boolean
     */
    private function writeConfigFile()
    {
        $config_file = @fopen(XGP_ROOT . CONFIGS_PATH . 'config.php', "w");

        if (!$config_file) {
            return false;
        }

        $data = "<?php\n";
        $data .= "defined('DB_HOST') ? null : define('DB_HOST', '" . $this->host . "');\n";
        $data .= "defined('DB_USER') ? null : define('DB_USER', '" . $this->user . "');\n";
        $data .= "defined('DB_PASS') ? null : define('DB_PASS', '" . $this->password . "');\n";
        $data .= "defined('DB_NAME') ? null : define('DB_NAME', '" . $this->name . "');\n";
        $data .= "defined('DB_PREFIX') ? null : define('DB_PREFIX', '" . $this->prefix . "');\n";
        $data .= "defined('SECRETWORD') ? null : define('SECRETWORD', 'xgp-" . StringsHelper::randomString(16) . "');\n";
        $data .= "?>";

        // create the new file
        if (fwrite($config_file, $data)) {
            fclose($config_file);

            return true;
        }

        // check if something was created and delete it
        if (file_exists($config_file)) {
            unlink($config_file);
        }

        return false;
    }

    /**
     * insertDbData
     *
     * @return boolean
     */
    private function insertDbData()
    {
        // init
        $tables = [];

        // get the database structure
        require_once XGP_ROOT . PUBLIC_PATH . 'install' . DIRECTORY_SEPARATOR . 'database.php';

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->Installation_Model->setWindowsSqlMode();
        }

        /**
         * Do table creations here...
         */
        foreach ($tables as $table => $query) {
            // run query for each table
            $status[$table] = $this->Installation_Model->runSimpleQuery($query);

            // if something fails... return false
            if ($status[$table] != 1) {
                return false;
            }
        }
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->Installation_Model->setNormalMode();
        }

        // ok!
        return true;
    }

    /**
     * createAccount
     *
     * @return negative value if an error ocurred, or 0 if admin account was successfully created
     *          -1: Some field is empty
     *          -2: Admin email is invalid
     */
    private function createAccount()
    {
        // validations
        if (empty($_POST['adm_user']) || empty($_POST['adm_pass']) || empty($_POST['adm_email'])) {
            return -1;
        }

        if (!Functions::validEmail($_POST['adm_email'])) {
            return -2;
        }

        // some default values
        $adm_name = $this->Installation_Model->escapeValue($_POST['adm_user']);
        $adm_email = $this->Installation_Model->escapeValue($_POST['adm_email']);
        $adm_pass = Functions::hash($_POST['adm_pass']);

        // create user and its planet
        parent::$users->createUserWithOptions(
            [
                'user_name' => $adm_name,
                'user_password' => $adm_pass,
                'user_email' => $adm_email,
                'user_authlevel' => '3',
                'user_home_planet_id' => '1',
                'user_galaxy' => 1,
                'user_system' => 1,
                'user_planet' => 1,
                'user_current_planet' => 1,
                'user_register_time' => time(),
                'user_ip_at_reg' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => '',
                'user_current_page' => '',
            ]
        );

        $this->_planet->setNewPlanet(1, 1, 1, 1, $adm_name);

        // write the new admin email for support
        Functions::updateConfig('admin_email', $adm_email);

        return true;
    }

    /**
     * validateDbData
     *
     * @return boolean
     */
    private function validateDbData()
    {
        return (!empty($this->host) && !empty($this->name) && !empty($this->user) && !empty($this->prefix));
    }

    /**
     * generateToken
     *
     * return string
     */
    private function generateToken()
    {
        $characters = 'aazertyuiopqsdfghjklmwxcvbnAZERTYUIOPQSDFGHJKLMWXCVBN1234567890';
        $count = strlen($characters);
        $new_token = '';
        $lenght = 16;
        srand((double) microtime() * 1000000);

        for ($i = 0; $i < $lenght; $i++) {
            $character_boucle = mt_rand(0, $count - 1);
            $new_token = $new_token . substr($characters, $character_boucle, 1);
        }

        return $new_token;
    }

    /**
     * saveMessage
     *
     * @param string $message Message
     * @param string $result  Result
     *
     * @return array
     */
    private function saveMessage($message, $result = 'ok')
    {
        switch ($result) {
            case 'ok':
                $parse['color'] = 'alert-success';
                $parse['status'] = $this->langs->line('ins_ok_title');
                break;

            case 'error':
                $parse['color'] = 'alert-error';
                $parse['status'] = $this->langs->line('ins_error_title');
                break;

            case 'warning':
                $parse['color'] = 'alert-block';
                $parse['status'] = $this->langs->line('ins_warning_title');
                break;
        }

        $parse['message'] = $message;

        return $this->getTemplate()->set(
            'install/save_message_view',
            $parse
        );
    }
}

/* end of installation.php */
