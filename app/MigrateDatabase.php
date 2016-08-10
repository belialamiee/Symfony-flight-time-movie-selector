<?php
/**
 * Command to migrate the translations from the Programs tables and translation files into the LocaleDefinition and LocaleTranslation tables in the database.
 * @author Ben Pratt <bpratt@rockinfo.com.au>
 * @copyright Rockwell Information Services
 */

namespace app;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

//override the two globals used in the customer front language files

$config = parse_ini_file('db.ini');
define('APPLICATION_PATH', $config['application_path']);
define('CUSTOMER_APPLICATION_PATH', $config['customer_path']);


class MigrateDatabase extends Command
{
    /**
     * @var int
     */
    protected $definitionCount = 0;
    /**
     * @var int
     */
    protected $translationCount = 0;
    /**
     * @var \PDO
     */
    protected $pdo;

    //sets up the command with an option to call via directory
    protected function configure()
    {
        $this->setName('migrate:database')
            ->setDescription('Migrate translations from the database')
            ->addArgument(
                'dirName',
                InputArgument::OPTIONAL,
                'The base directory of the application'
            );
    }

    //runs the command to go through and add definitions and translations to the database.
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getPDOConnection();
        $dirName = $input->getArgument('dirName');
        //if we have a dirName as an argument then use that and we go through each file in this directory
        if ($dirName) {
            //strip closing slash if there is one
            if (substr($dirName, -1) == '/') {
                $dirName = substr($dirName, 0, -1);
            }
            $output->writeln('<comment>Importing from file.</comment>');
            //get the files for each section that has translations.
            $files = scandir($dirName . "/app/application/language");
            $this->processDirectory($dirName . "/app/application/language", $files);
            //get the front end language files, The customer overrides includes the Eolas translations so no need to call both
            $files = scandir($dirName . '/app/customer/application/language/front');
            $this->processDirectory($dirName . '/app/customer/application/language/front', $files);


            // otherwise we use database and look at the programs and set those as definitions.
        } else {
            $output->writeln('<comment>Importing from database for programs.</comment>');
            //get the program names and descriptions
            $entries = $this->getProgramsFromDatabase();
            foreach ($entries as $entry) {
                //save them as definitions.
                if ($entry['name']) {
                    $this->saveDefinition($entry['name'], null);
                }
                if ($entry['description']) {
                    $this->saveDefinition($entry['description'], null);
                }
            }

            $output->writeln('<comment>Importing from database for program tiers.</comment>');
            //get the program tier names and descriptions
            $entries = $this->getProgramTiersFromDatabase();
            foreach ($entries as $entry) {
                //save them as definitions.
                if ($entry['name']) {
                    $this->saveDefinition($entry['name'], null);
                }
                if ($entry['description']) {
                    $this->saveDefinition($entry['description'], null);
                }
            }

            $output->writeln('<comment>Importing from database for events.</comment>');
            //get the program names and descriptions
            $entries = $this->getEventDetailsFromDatabase();
            foreach ($entries as $entry) {

                //save the definitions.
                if ($entry['event_detail_title']) {
                    if ($entry['event_detail_language_id'] == 1) {
                        $this->saveDefinition($entry['event_detail_title'], null);
                    } else {
                        //else we need to get the english version of this, and then get the title so that we can get the definitions
                        $englishDetail = $this->getEnglishEventDetails($entry['event_detail_event_id']);
                        //this will return the definition id if it exists, else it will save this definition first anyway
                        $id = $this->saveDefinition($englishDetail['event_detail_title'], null);
                        $this->saveTranslation($id, $entry['event_detail_title'], $this->getLanguage($entry['event_detail_language_id']));
                    }
                }
                if ($entry['event_detail_description']) {
                    if ($entry['event_detail_language_id'] == 1) {
                        $this->saveDefinition($entry['event_detail_description'], null);
                    } else {
                        //else we need to get the english version of this, and then get the description so that we can get the definitions
                        $englishDetail = $this->getEnglishEventDetails($entry['event_detail_event_id']);
                        //this will return the definition id if it exists, else it will save this definition first anyway
                        $id = $this->saveDefinition($englishDetail['event_detail_description'], null);
                        $this->saveTranslation($id, $entry['event_detail_description'],$this->getLanguage($entry['event_detail_language_id']));
                    }
                }
            }
        }
        $output->writeln('<info>Finished Importing ' . $this->definitionCount . ' new definitions and '
            . $this->translationCount . ' new translations</info>');
    }

    /**
     * Get the contents from a file this should come in as an array
     * @param string $fileString
     * @var array $translations
     * @return array
     */
    protected function getFromFile(
        $fileString
    ) {
        $translations = include($fileString);
        return $translations;
    }

    /**
     * Get the contents of the program table from the database
     * @return array
     */
    protected function getProgramsFromDatabase()
    {
        $sql = "SELECT * FROM program";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get the contents of the program_tier table from database
     * @return array
     */
    protected function getProgramTiersFromDatabase()
    {
        $sql = "SELECT * FROM program_tier";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Gets the event titles and descriptions from the event details section of the database
     * @return array
     */
    protected function getEventDetailsFromDatabase()
    {
        //Get all events that do not have a legacyId on them (All events past this are over two years old)
        //Potentially we could change this to all events in the last 3-12 months by targeting the schedule or event_created date instead.
        $sql = "SELECT ed.* FROM lms_event_detail ed
                  INNER JOIN lms_event e ON ed.event_detail_event_id = e.event_id AND e.event_legacy_id IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Gets a connection to the database form the db.ini
     */
    protected function getPDOConnection()
    {
        $config = parse_ini_file('db.ini');
        $db = $config['db'];
        $username = $config['username'];
        $password = $config['password'];
        $this->pdo = new \PDO($db, $username, $password);
    }

    /**
     * Saves a new definition into the database, or returns the definition id if one exists
     * @param string $value
     * @param string $key
     * @return int
     */
    protected function saveDefinition(
        $value,
        $key
    ) {
        if ($key) {
            $sql = "SELECT * FROM LocaleDefinition WHERE variant = :variant";
            $params['variant'] = $key;
        } else {
            $params = ['value' => $value];
            $sql = "SELECT * FROM LocaleDefinition WHERE value=:value AND variant IS NULL";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $matches = $stmt->fetchAll();

        $params['value'] = $value;
        //if we have a blank $value but we have a $key then insert the key as the value.
        if ($key && $value == "") {
            $params['value'] = $key;
        }
        //if no match then we can insert it and return the new id, else we just return the id.
        if (!$matches) {
            $this->definitionCount++;
            if ($key) {
                $insert = 'INSERT INTO LocaleDefinition VALUES(NULL, :value , :variant, NOW(), NOW())';
            } else {
                $insert = 'INSERT INTO LocaleDefinition VALUES(NULL, :value , NULL, NOW(), NOW())';
            }
            $stmt = $this->pdo->prepare($insert);
            $stmt->execute($params);
            $definitionId = $this->pdo->lastInsertId();
        } else {
            $definitionId = $matches[0]['id'];
        }
        return $definitionId;
    }


    /**
     * Save a new Translation or update an existing one. Content from this is coming from files containing arrays
     * @param int $definitionId
     * @param string $translation
     * @param string $locale
     */
    public function saveTranslation(
        $definitionId,
        $translation,
        $locale
    ) {
        $params = ["definitionId" => $definitionId, "locale" => $locale];
        //check first if we have a match
        $searchSql = "SELECT * FROM LocaleTranslation WHERE definition_id = :definitionId AND locale= :locale";
        $stmt = $this->pdo->prepare($searchSql);
        $stmt->execute($params);
        $params["translation"] = $translation;
        $match = $stmt->fetchAll();
        if (!$match) {
            $sql = "INSERT INTO LocaleTranslation VALUES(NULL,:locale, :definitionId,:translation,NOW(),NOW())";
            $this->translationCount++;
        } else {
            $sql = "UPDATE LocaleTranslation SET translation = :translation WHERE definition_id = :definitionId AND locale = :locale";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Processes the files in a directory, for each element in the array of each file,
     * it adds a definition and/or a translation to the database
     * @param string $dirName name of the directory
     * @param array $files array of files to be processed
     */
    protected function processDirectory(
        $dirName,
        $files
    ) {
        foreach ($files as $file) {
            //ignore . and .. as these are not files we want to use.
            //Also ignore the "front" directory as this is not a file and will cause an exception,
            //The files in the front folder are being covered by the customer front end file which includes and array merges them to remove duplicates
            //The programsen.php and programsen.php file are being included as above, but they should not be.....
            // May have to delete these two files before we run this for as they will not be required afterwards anyway

            if ($file != "." && $file != ".." && $file != 'front' && strpos($file, "program") === false) {
                //get the entries as an array
                $entries = $this->getFromFile($dirName . '/' . $file);
                //save definitions and/or a translation.
                foreach ($entries as $entryName => $entry) {
                    $locale = basename($file, ".php");
                    if ($locale != 'en') {
                        //this stops translations without english from having say a japanese definition.
                        $definitionId = $this->saveDefinition($entryName, $entryName);
                    } else {
                        $definitionId = $this->saveDefinition($entry, $entryName);
                    }
                    $this->saveTranslation($definitionId, $entry, $locale);
                }
            }
        }
    }

    /**
     * gets the language acronym for the language
     * @param int $languageId
     * @return array
     */
    protected function getLanguage($languageId){

        $sql = "SELECT * FROM lms_language WHERE language_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id'=>$languageId]);
        $language = $stmt->fetchAll();
        return $language[0]['language_acronym'];
    }

    /**
     * Gets the event details in english so that we can save the original title/get the definition id.
     * @param int $eventId
     * @return array
     */
    public function getEnglishEventDetails($eventId){
        $sql = "SELECT * FROM lms_event_detail WHERE event_detail_language_id = 1 AND event_detail_event_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id'=>$eventId]);
        $eventDetail = $stmt->fetchAll();
        return $eventDetail[0];
    }

}