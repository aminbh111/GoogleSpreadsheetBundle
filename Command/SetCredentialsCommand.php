<?php

namespace Dreamlex\Bundle\GoogleSpreadsheetBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class SetCredentialsCommand
 *
 * @package Dreamlex\Bundle\GoogleSpreadsheetBundle\Command
 */
class SetCredentialsCommand extends ContainerAwareCommand {

    protected function configure() {
        $this
                ->setName('google-spreadsheet:set-credentials')
                ->setDescription('Set credentials for google spreadsheet');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $googleSpreadsheet = $this->getContainer()->get('dreamlex_google_spreadsheet');

        if (false === $googleSpreadsheet->isCredentialsExisted()) {
            $googleSpreadsheetClient = $googleSpreadsheet->getClient();
            // Request authorization from the user.
            $authUrl = $googleSpreadsheetClient->createAuthUrl();

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            $question = new Question(sprintf("<question>Open the following link in your browser:\n%s\n</question>", $authUrl));

            $authCode = $helper->ask($input, $output, $question);

            // Exchange authorization code for an access token.
            $accessToken = $googleSpreadsheetClient->authenticate($authCode);

            $credentialsFilename = $googleSpreadsheet->saveCredentials($accessToken);

            $output->writeln(sprintf('<info>Credentials saved in %s</info>', $credentialsFilename));
        } else {
            $output->writeln(sprintf('<info>Credentials already existed in %s</info>', $googleSpreadsheet->getCredentialsFilename()));
        }
    }

}
