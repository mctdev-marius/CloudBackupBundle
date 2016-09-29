<?php
namespace Dizda\CloudBackupBundle\Database;

use Symfony\Component\Process\ProcessUtils;

/**
 * Class MySQL.
 *
 * @author  Jonathan Dizdarevic <dizda@dizda.fr>
 */
class MySQL extends BaseDatabase
{
    const DB_PATH = 'mysql';

    private $allDatabases;
    private $database;
    private $auth = '';
    private $fileName;
    private $ignoreTables = '';

    /**
     * DB Auth.
     *
     * @param array  $params
     * @param string $basePath
     */
    public function __construct($params, $basePath)
    {
        parent::__construct($basePath);

        $params = $params['mysql'];
        $this->allDatabases = $params['all_databases'];
        $this->database     = $params['database'];
        $this->auth         = '';

        if ($this->allDatabases) {
            $this->database = '--all-databases';
            $this->fileName = 'all-databases.sql';
        } else {
            $this->fileName = $this->database.'.sql';
        }

        if (isset($params['ignore_tables'])) {
            foreach ($params['ignore_tables'] as $ignoreTable) {
                if ($this->allDatabases) {
                    if (false === strpos($ignoreTable, '.')) {
                        throw new \LogicException(
                            'When dumping all databases both database and table must be specified when ignoring table'
                        );
                    }
                    $this->ignoreTables .= sprintf('--ignore-table=%s ', $ignoreTable);
                } else {
                    $this->ignoreTables .= sprintf('--ignore-table=%s.%s ', $this->database, $ignoreTable);
                }
            }
        }

        /* if user is set, we add authentification */
        if ($params['db_user']) {
            $cnfFile            = "[client]\n";
            $cnfPath            = $basePath."mysql.cnf";
            $cnfParams['user']  = $params['db_user'];

            if ($params['db_password']) {
                $cnfParams = array_merge(
                    $cnfParams,
                    array(
                        "password" => $params['db_password'],
                        "host" => $params['db_host'],
                        "port" => $params['db_port']
                    )
                );
            }

            foreach ($cnfParams as $key => $value) {
                $cnfFile .= "$key = \"$value\"\n";
            }

            $this->filesystem->dumpFile($cnfPath, $cnfFile, 0600);
            $this->auth = sprintf("--defaults-extra-file=\"%s\" ", $cnfPath);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dump()
    {
        $this->preparePath();
        $this->execute($this->getCommand());
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommand()
    {
        return sprintf('mysqldump %s %s %s > %s',
            $this->auth,
            $this->database,
            $this->ignoreTables,
            ProcessUtils::escapeArgument($this->dataPath.$this->fileName)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'MySQL';
    }
}
