<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

use Shopware\Core\Maintenance\System\Service\DatabaseInitializer;
use Shopware\Recovery\Common\IOHelper;
use Shopware\Recovery\Install\Struct\DatabaseConnectionInformation;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class DatabaseInteractor
{
    private IOHelper $IOHelper;

    public function __construct(IOHelper $IOHelper)
    {
        $this->IOHelper = $IOHelper;
    }

    public function askDatabaseConnectionInformation(
        DatabaseConnectionInformation $connectionInformation
    ): DatabaseConnectionInformation {
        $databaseHost = $this->askForDatabaseHostname($connectionInformation->getHostname());
        $databasePort = $this->askForDatabasePort($connectionInformation->getPort());
        $databaseUser = $this->askForDatabaseUsername($connectionInformation->getUsername());
        $databasePassword = $this->askForDatabasePassword($connectionInformation->getPassword());

        $dbSslCa = $this->IOHelper->ask('Please enter database SSL CA path: ', '');
        $dbSslCert = $this->IOHelper->ask('Please enter database SSL cerificate path: ', '');
        $dbSslKey = $this->IOHelper->ask('Please enter database SSL key path: ', '');
        $dbSslDontVerify = $this->IOHelper->askConfirmation(new ConfirmationQuestion('Don\'t verify database server certificate?'));

        return (new DatabaseConnectionInformation())->assign([
            'hostname' => $databaseHost,
            'port' => $databasePort,
            'username' => $databaseUser,
            'password' => $databasePassword,
            'sslCaPath' => $dbSslCa,
            'sslCertPath' => $dbSslCert,
            'sslCertKeyPath' => $dbSslKey,
            'sslDontVerifyServerCert' => $dbSslDontVerify ? true : false,
        ]);
    }

    public function createDatabase(DatabaseInitializer $initializer): string
    {
        $question = new Question('Please enter the name database to be created: ');
        $databaseName = $this->askQuestion($question);

        $initializer->createDatabase($databaseName);

        return $databaseName;
    }

    /**
     * @return bool|string|null
     */
    public function continueWithExistingTables(string $databaseName, DatabaseInitializer $initializer)
    {
        $tableCount = $initializer->getTableCount($databaseName);
        if ($tableCount === 0) {
            return true;
        }

        $question = new ConfirmationQuestion(
            sprintf(
                'The database %s already contains %s tables. Continue? (yes/no) [no]',
                $databaseName,
                $tableCount
            ),
            false
        );

        return $this->askQuestion($question);
    }

    /**
     * @return bool|string|null
     */
    public function askQuestion(Question $question)
    {
        return $this->IOHelper->ask($question);
    }

    protected function askForDatabaseHostname(string $defaultHostname): string
    {
        $question = new Question(sprintf('Please enter database host (%s): ', $defaultHostname), $defaultHostname);
        $question->setValidator(
            function ($answer) {
                if (trim((string) $answer) === '') {
                    throw new \Exception('The database user can not be empty');
                }

                return $answer;
            }
        );

        return (string) $this->askQuestion($question);
    }

    protected function askForDatabaseUsername(?string $defaultUsername): string
    {
        if (empty($defaultUsername)) {
            $question = new Question('Please enter database user: ');
        } else {
            $question = new Question(sprintf('Please enter database user (%s): ', $defaultUsername), $defaultUsername);
        }

        $question->setValidator(
            static function ($answer) {
                if (trim((string) $answer) === '') {
                    throw new \Exception('The database user can not be empty');
                }

                return $answer;
            }
        );

        return (string) $this->askQuestion($question);
    }

    protected function askForDatabasePassword(?string $defaultPassword): string
    {
        if (empty($defaultPassword)) {
            $question = new Question('Please enter database password: ');
        } else {
            $question = new Question(sprintf('Please enter database password: (%s): ', $defaultPassword), $defaultPassword);
        }

        return (string) $this->askQuestion($question);
    }

    private function askForDatabasePort(int $defaultPort): string
    {
        $question = new Question(sprintf('Please enter database port (%s): ', $defaultPort), $defaultPort);
        $question->setValidator(
            static function ($answer) {
                if (trim((string) $answer) === '') {
                    throw new \Exception('The database port can not be empty');
                }

                if (!is_numeric($answer)) {
                    throw new \Exception('The database port must be a number');
                }

                return $answer;
            }
        );

        return (string) $this->askQuestion($question);
    }
}
