<?php
/**
 * Command to Select movies for in-flight entertainment
 * @author Ben Pratt <bpratt2@myune.edu.au>
 */

namespace app;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SelectMovies extends Command
{
    /**
     * @var int
     */
    private $movieOptions = [
        ["name" => "Batman", "length" => 125],
        ["name" => "Suicide Squad", "length" => 132],
        ["name" => "Austin Powers", "length" => 75],
        ["name" => "Oceans Eleven", "length" => 46],
        ["name" => "Kill Bill", "length" => 175],
        ["name" => "The Seven Samurai", "length" => 133],
        ["name" => "Dr Strangelove", "length" => 118],
        ["name" => "The Big Lebowski", "length" => 196],
        ["name" => "Gone With The Wind", "length" => 154],
        ["name" => "Absolutely Fabulous", "length" => 122],
        ["name" => "Rocky", "length" => 87],
        ["name" => "Rambo", "length" => 125]
    ];


    //sets up the command with an option
    protected function configure()
    {
        $this->setName('select:movies')
            ->setDescription('Select Movies for in-flight entertainment')
            ->addArgument(
                'flightLength',
                InputArgument::OPTIONAL,
                'The duration of the passengers flight'
            );
    }

    //runs the command to go through and add definitions and translations to the database.
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bestMatch = [];
        $flightLength = $input->getArgument('flightLength');
        if ($flightLength) {
            //ensure we have a number as a flight time
            if (is_numeric($flightLength)) {

                $bestMatch = $this->findBestMatch($flightLength);
                $bestMatch = implode(", ", $bestMatch);

            } else {
                $output->writeln('<comment>Flight time should be a number.</comment>');
            }
        } else {
            $output->writeln('<comment>No flight time was provided.</comment>');

        }
        $output->writeln('<info> Your movies should be ' . $bestMatch . '</info>');
    }


    public function findBestMatch($flightLength, $movies = [], $runningTime = 0)
    {
        $movieToAdd = null;
        $minimumMovieTime = 400;
        //fill the slot with the largest movie that will fit the timeslot
        foreach ($this->movieOptions as $movie) {
            if ($movie['length'] > $runningTime && $movie['length'] < $flightLength) {
                $runningTime = $movie['length'];
                $movieToAdd = $movie['name'] . " " . $runningTime . "mins";
            }
            if ($movie['length'] < $minimumMovieTime) {
                $minimumMovieTime = $movie['length'];
            }
        }

        if ($movieToAdd) {
            $movies[] = $movieToAdd;
        }
        //if we have enough time for another movie then recursively call this function
        if (($flightLength - $runningTime) > $minimumMovieTime) {
            return $this->findBestMatch($flightLength - $runningTime, $movies);
        } else {
            return $movies;
        }
    }
}