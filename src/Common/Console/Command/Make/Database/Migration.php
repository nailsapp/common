<?php

namespace Nails\Common\Console\Command\Make\Database;

use Nails\Common\Console\Command\Database\Migrate;
use Nails\Common\Console\Command\Install\Components;
use Nails\Common\Exception\NailsException;
use Nails\Common\Exception\Console\MigrationExistsException;
use Nails\Config;
use Nails\Console\Command\BaseMaker;
use Nails\Factory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Migration
 *
 * @package Nails\Common\Console\Command\Make\Database
 */
class Migration extends BaseMaker
{
    const RESOURCE_PATH  = NAILS_COMMON_PATH . 'resources/console/';
    const MIGRATION_PATH = NAILS_APP_PATH . 'src/Database/Migration/';

    // --------------------------------------------------------------------------

    /**
     * Configure the command
     */
    protected function configure(): void
    {
        $this
            ->setName('make:db:migration')
            ->setDescription('Creates a new Database Migration')
            ->addOption(
                'sql-on-zero',
                null,
                InputOption::VALUE_OPTIONAL,
                'Automatically populate the migration when creating the first migration, i.e. 0',
                true
            )
            ->addArgument(
                'index',
                InputArgument::OPTIONAL,
                'The migration index, leave blank to auto-detect'
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Executes the app
     *
     * @param InputInterface  $oInput  The Input Interface provided by Symfony
     * @param OutputInterface $oOutput The Output Interface provided by Symfony
     *
     * @return int
     */
    protected function execute(InputInterface $oInput, OutputInterface $oOutput): int
    {
        parent::execute($oInput, $oOutput);

        // --------------------------------------------------------------------------

        try {
            //  Ensure the paths exist
            $this->createPath(self::MIGRATION_PATH);
            //  Create the controller
            $this->createMigration();
        } catch (\Exception $e) {
            return $this->abort(
                self::EXIT_CODE_FAILURE,
                [$e->getMessage()]
            );
        }

        // --------------------------------------------------------------------------

        //  Cleaning up
        $oOutput->writeln('');
        $oOutput->writeln('<comment>Cleaning up</comment>...');

        // --------------------------------------------------------------------------

        //  And we're done
        $oOutput->writeln('');
        $oOutput->writeln('Complete!');

        return self::EXIT_CODE_SUCCESS;
    }

    // --------------------------------------------------------------------------

    /**
     * Create the Model
     *
     * @return void
     * @throws \Exception
     */
    private function createMigration(): void
    {
        try {

            $oNow    = Factory::factory('DateTime');
            $aFields = [
                'INDEX'      => $this->oInput->getArgument('index'),
                'DATE_START' => $oNow->format('Y-m-d'),
            ];

            if (is_null($aFields['INDEX'])) {

                $aMigrations = Migrate::getMigrationsForComponent(
                    \Nails\Components::getApp(),
                    Factory::service('PDODatabase')
                );

                $oLastMigration   = end($aMigrations);
                $aFields['INDEX'] = empty($oLastMigration)
                    ? 0
                    : $oLastMigration->getPriority() + 1;

            } elseif (!is_numeric($aFields['INDEX'])) {
                throw new NailsException('Specified migration index is not a numeric value.');
            } else {
                $aFields['INDEX'] = (int) $aFields['INDEX'];
            }

            $this->oOutput->write('Creating migration <comment>' . $aFields['INDEX'] . '</comment>... ');

            //  Check for existing controller
            $sPath = static::MIGRATION_PATH . 'Migration' . $aFields['INDEX'] . '.php';
            if (file_exists($sPath)) {
                throw new MigrationExistsException(
                    'Migration "' . $aFields['INDEX'] . '" exists already at path "' . $sPath . '"'
                );
            }

            //  If we're making the first migration, get a dump of all the non-Nails tables
            $aFields['QUERIES'] = '';
            if ($aFields['INDEX'] === 0 && stringToBoolean($this->oInput->getOption('sql-on-zero'))) {

                $oDb      = Factory::service('Database');
                $aResult  = $oDb->query('SHOW TABLES')->result();
                $aCreates = [];

                foreach ($aResult as $oResult) {
                    $aResult = (array) $oResult;
                    $sTable  = reset($aResult);
                    if (!preg_match('/^' . Config::get('NAILS_DB_PREFIX') . '/', $sTable) || $sTable == Config::get('NAILS_DB_PREFIX') . 'user_meta_app') {
                        $aResult    = (array) $oDb->query('SHOW CREATE TABLE ' . $sTable)->row();
                        $aCreates[] = 'DROP TABLE IF EXISTS ' . $aResult['Table'] . ';' . "\n" . $aResult['Create Table'];
                    }
                }

                foreach ($aCreates as $sCreate) {

                    $aCreate = explode("\n", $sCreate);
                    $iCount  = count($aCreate);
                    array_walk(
                        $aCreate,
                        function (&$sLine, $iIndex) use ($iCount) {
                            $sLine = trim($sLine);
                            if ($iIndex > 1 && $iIndex < ($iCount - 1)) {
                                $sLine = $this->tabs(4) . $sLine;
                            } else {
                                $sLine = $this->tabs(3) . $sLine;
                            }
                        }
                    );

                    $aFields['QUERIES'] .= $this->tabs(2) . '$this->query(\'' . "\n";
                    $aFields['QUERIES'] .= str_replace("'", "\'", implode("\n", $aCreate)) . "\n";
                    $aFields['QUERIES'] .= $this->tabs(2) . '\');' . "\n";
                }

                $aFields['QUERIES'] = trim($aFields['QUERIES']);
                $aFields['QUERIES'] = str_replace(Config::get('NAILS_DB_PREFIX'), '{{NAILS_DB_PREFIX}}', $aFields['QUERIES']);
                $aFields['QUERIES'] = str_replace(Config::get('APP_DB_PREFIX'), '{{APP_DB_PREFIX}}', $aFields['QUERIES']);
                $aFields['QUERIES'] = preg_replace('/AUTO_INCREMENT=\d+ /', '', $aFields['QUERIES']);

            } else {
                $aFields['QUERIES'] = '$this->query(\'\');';
            }

            $this->createFile($sPath, $this->getResource('template/migration.php', $aFields));
            $aCreated[] = $sPath;
            $this->oOutput->writeln('<info>done</info>');

        } catch (\Exception $e) {
            $this->oOutput->writeln('<error>fail</error>');
            //  Clean up created models
            if (!empty($aCreated)) {
                $this->oOutput->writeln('<error>Cleaning up - removing newly created migration</error>');
                foreach ($aCreated as $sPath) {
                    @unlink($sPath);
                }
            }
            throw new NailsException($e->getMessage());
        }
    }
}
