<?php

namespace Modules\Aggiornamenti;

use Auth\Group;
use Controllers\Config\RequirementsController as Requirements;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Modules;
use Modules\Module;
use Parsedown;
use Symfony\Component\Finder\Finder;
use Update;
use Util\Ini;
use Util\Zip;

class Aggiornamento
{
    protected static $client = null;

    protected $directory = null;
    protected $components = null;

    protected $groups = null;

    /**
     * Crea l'istanza dedicata all'aggiornamento presente nella cartella indicata.
     *
     * @param string $directory
     *
     * @throws InvalidArgumentException
     */
    public function __construct($directory = null)
    {
        $this->directory = $directory ?: Zip::getExtractionDirectory();

        if (!$this->isCoreUpdate() && empty($this->componentUpdates())) {
            throw new InvalidArgumentException();
        }
    }

    /**
     * Pulisce la cartella di estrazione.
     */
    public function delete()
    {
        delete($this->directory);
    }

    /**
     * Restituisce il percorso impostato per l'aggiornamento corrente.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Controlla se l'aggiornamento è di tipo globale.
     *
     * @return bool
     */
    public function isCoreUpdate()
    {
        return file_exists($this->directory.'/VERSION');
    }

    public function getChangelog()
    {
        if ($this->isCoreUpdate()) {
            $changelog = self::readChangelog($this->getDirectory(), Update::getVersion());
        } else {
            $changelogs = [];
            $elements = $this->componentUpdates();

            $list = array_merge($elements['modules'], $elements['plugins']);
            foreach ($list as $element) {
                $changelog = self::readChangelog($element['path'], $element['version']);

                if (!empty($changelog)) {
                    $changelogs[] = '
                <h4 class="text-center">'.$element['info']['name'].'<h4>
                '.$changelog;
                }
            }

            $changelog = implode('<hr>', $changelogs);
        }

        return $changelog;
    }

    public function getRequirements()
    {
        $file = $this->directory.'/config/requirements.php';
        $result = Requirements::getRequirementsList($file);

        return $result;
    }

    public function getVersion()
    {
        return Update::getFile($this->getDirectory().'/VERSION');
    }

    /**
     * Individua i componenti indipendenti che compongono l'aggiornamento.
     *
     * @return array
     */
    public function componentUpdates()
    {
        if (!isset($this->components)) {
            $finder = Finder::create()
                ->files()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->in($this->directory);

            $files = $finder->name('MODULE')->name('PLUGIN');

            $results = [];
            foreach ($files as $file) {
                $is_module = basename($file->getRealPath()) == 'MODULE';
                $is_plugin = basename($file->getRealPath()) == 'PLUGIN';

                $info = Ini::readFile($file->getRealPath());
                $installed = \Modules\Module::get($info['name']);
                if ($is_module) {
                    $type = 'modules';
                } elseif ($is_plugin) {
                    $type = 'plugins';
                }

                if (!isset($results[$type])) {
                    $results[$type] = [];
                }
                $results[$type][] = [
                    'path' => dirname($file->getRealPath()),
                    'config' => $file->getRealPath(),
                    'is_installed' => !empty($installed),
                    'current_version' => !empty($installed) ? $installed->version : null,
                    'info' => $info,
                ];
            }

            $this->components = $results;
        }

        return $this->components;
    }

    /**
     * Effettua l'aggiornamento.
     */
    public function execute()
    {
        if ($this->isCoreUpdate()) {
            $this->executeCore();
        } else {
            $components = $this->componentUpdates();

            foreach ((array) $components['modules'] as $module) {
                $this->executeModule($module);
            }

            foreach ((array) $components['plugins'] as $plugin) {
                $this->executeModule($plugin);
            }
        }

        $this->delete();
    }

    /**
     * Completa l'aggiornamento globale secondo la procedura apposita.
     */
    public function executeCore()
    {
        // Salva il file di configurazione
        $config = file_get_contents(DOCROOT.'/config.inc.php');

        // Copia i file dalla cartella temporanea alla root
        copyr($this->directory, DOCROOT);

        // Ripristina il file di configurazione dell'installazione
        file_put_contents(DOCROOT.'/config.inc.php', $config);
    }

    /**
     * Completa l'aggiornamento con le informazioni specifiche per i moduli.
     *
     * @param array $module
     */
    public function executeModule($module)
    {
        // Informazioni dal file di configurazione
        $info = $module['info'];

        // Informazioni aggiuntive per il database
        $insert = [
            'parent' => \Modules\Module::get($info['parent']),
            'icon' => $info['icon'],
        ];

        $id = $this->executeComponent($module['path'], 'modules', 'zz_modules', $insert, $info, $module['is_installed']);

        if (!empty($id)) {
            // Fix per i permessi di amministratore
            $element = Module::find($id);

            $element->groups()->syncWithoutDetaching($this->groups());
        }
    }

    /**
     * Instanzia un aggiornamento sulla base di uno zip indicato.
     * Controlla inoltre che l'aggiornamento sia fattibile.
     *
     * @param string $file
     *
     * @throws DowngradeException
     * @throws InvalidArgumentException
     *
     * @return static
     */
    public static function make($file)
    {
        $extraction_dir = Zip::extract($file);

        $update = new static($extraction_dir);

        if ($update->isCoreUpdate()) {
            $version = Update::getFile($update->getDirectory().'/VERSION');
            $current = Update::getVersion();

            if (version_compare($current, $version) >= 0) {
                $update->delete();

                throw new DowngradeException();
            }
        } else {
            $components = $update->componentUpdates();

            foreach ((array) $components['modules'] as $module) {
                if (version_compare($module['current_version'], $module['info']['version']) >= 0) {
                    delete($module['path']);
                }
            }

            foreach ((array) $components['plugins'] as $plugin) {
                if (version_compare($plugin['current_version'], $plugin['info']['version']) >= 0) {
                    delete($plugin['path']);
                }
            }
        }

        // Instanzia nuovamente l'oggetto
        return new static($extraction_dir);
    }

    /**
     * Controlla se è disponibile un aggiornamento nella repository GitHub.
     *
     * @return string|bool
     */
    public static function isAvailable()
    {
        $api = self::getAPI();

        $version = ltrim($api['tag_name'], 'v');
        $current = Update::getVersion();

        $result = false;
        if (version_compare($current, $version) < 0) {
            $result = $version;
        }

        UpdateHook::update($result);

        return $result;
    }

    /**
     * Scarica la release più recente (se presente).
     *
     * @return static
     */
    public static function download()
    {
        if (self::isAvailable() === false) {
            return null;
        }

        $directory = Zip::getExtractionDirectory();
        $file = $directory.'/release.zip';
        directory($directory);

        $api = self::getAPI();
        self::getClient()->request('GET', $api['assets'][0]['browser_download_url'], ['sink' => $file]);

        $update = self::make($file);
        delete($file);

        return $update;
    }

    /**
     * Restituisce i contenuti JSON dell'API del progetto.
     *
     * @return array
     */
    public static function checkFiles()
    {
        $date = date('Y-m-d', filemtime(DOCROOT.'/core.php'));

        // Individuazione dei file tramite data di modifica
        $files = Finder::create()
            ->date('<= '.$date)
            ->sortByName()
            ->in(DOCROOT)
            ->exclude([
                'node_modules',
                'tests',
                'tmp',
                'vendor',
            ])
            ->name('*.php')
            ->notPath('*custom*')
            ->files();

        return iterator_to_array($files);
    }

    /**
     * Restituisce il changelog presente nel percorso indicato a partire dalla versione specificata.
     *
     * @param string $path
     * @param string $version
     *
     * @return string
     */
    protected static function readChangelog($path, $version = null)
    {
        $result = file_get_contents($path.'/CHANGELOG.md');

        $start = strpos($result, '## ');
        $result = substr($result, $start);
        if (!empty($version)) {
            $last = strpos($result, '## '.$version.' ');

            if ($last !== false) {
                $result = substr($result, 0, $last);
            }
        }

        $result = Parsedown::instance()->text($result);
        $result = str_replace(['h4>', 'h3>', 'h2>'], ['p>', 'b>', 'h4>'], $result);

        return $result;
    }

    /**
     * Completa l'aggiornamento del singolo componente come previsto dai parametri indicati.
     *
     * @param string $directory    Percorso di copia dei contenuti
     * @param string $table        Tabella interessata dall'aggiornamento
     * @param array  $insert       Informazioni per la registrazione
     * @param array  $info         Contenuti della configurazione
     * @param bool   $is_installed
     *
     * @return int|null
     */
    protected function executeComponent($path, $directory, $table, $insert, $info, $is_installed = false)
    {
        // Copia dei file nella cartella relativa
        copyr($path, DOCROOT.'/'.$directory.'/'.$info['directory']);

        // Eventuale registrazione nel database
        if (empty($is_installed)) {
            $dbo = database();

            $dbo->insert($table, array_merge($insert, [
                'name' => $info['name'],
                'title' => !empty($info['title']) ? $info['title'] : $info['name'],
                'directory' => $info['directory'],
                'options' => $info['options'],
                'version' => $info['version'],
                'compatibility' => $info['compatibility'],
                'order' => 100,
                'default' => 0,
                'enabled' => 1,
            ]));

            return $dbo->lastInsertedID();
        }
    }

    /**
     * Resituisce i permessi di default da impostare all'installazione del componente.
     *
     * @return array
     */
    protected function groups()
    {
        if (!isset($this->groups)) {
            $groups = Group::where('nome', 'Amministratori')->get();

            $result = [];
            foreach ($groups as $group) {
                $result[$group->id] = [
                    'permission_level' => 'rw',
                ];
            }

            $this->groups = $result;
        }

        return $this->groups;
    }

    /**
     * Restituisce l'oggetto per la connessione all'API del progetto.
     *
     * @return Client
     */
    protected static function getClient()
    {
        if (!isset(self::$client)) {
            self::$client = new Client([
                'base_uri' => 'https://api.github.com/repos/devcode-it/openstamanager/',
                'verify' => false,
            ]);
        }

        return self::$client;
    }

    /**
     * Restituisce i contenuti JSON dell'API del progetto.
     *
     * @return array
     */
    protected static function getAPI()
    {
        $response = self::getClient()->request('GET', 'releases');
        $body = $response->getBody();

        return json_decode($body, true)[0];
    }
}
