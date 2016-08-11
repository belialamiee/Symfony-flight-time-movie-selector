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

    //execute symfony console command.
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $results = "";
        $flightLength = $input->getArgument('flightLength');
        if ($flightLength) {
            //ensure we have a number as a flight time
            if (is_numeric($flightLength)) {
                $bestMatch = $this->findBestMatch($flightLength);
                foreach($bestMatch as $match){
                    $results .=  $match->title ." ".$match->runtime. " ";
                }
            } else {
                $output->writeln('<comment>Flight time should be a number.</comment>');
            }
        } else {
            $output->writeln('<comment>No flight time was provided.</comment>');

        }
        $output->writeln('<info> Your movies should be ' . $results. '</info>');
    }


    public function findBestMatch($flightLength, $movies = [], $runningTime = 0)
    {
        $movieToAdd = null;
        $minimumMovieTime = 400;
        //fill the slot with the largest movie that will fit the timeslot
        $options = json_decode(file_get_contents('/var/www/theRightFit/app/movies.json'));
        foreach($options as $movie){
            if ($movie->runtime > $runningTime && $movie->runtime < $flightLength && !in_array($movie,$movies)) {
                $runningTime = $movie->runtime;
                $movieToAdd = $movie;
            }

            if ($movie->runtime < $minimumMovieTime && $movie->runtime > 0) {
                $minimumMovieTime = $movie->runtime;
            }
        }

        if ($movieToAdd) {
            $movies[] = $movieToAdd;
        }
        if (($flightLength - $runningTime) > $minimumMovieTime) {
            return $this->findBestMatch($flightLength - $runningTime, $movies);
        } else {
            return $movies;
        }
    }
}