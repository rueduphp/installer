<?php
    namespace Rueduphp;

    use ZipArchive;
    use RuntimeException;
    use GuzzleHttp\Client;
    use Symfony\Component\Process\Process;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class Installer extends Command
    {
        /**
         * Configure the command options.
         *
         * @return void
         */
        protected function configure()
        {
            $this
                ->setName('new')
                ->setDescription('Create a new Octo skeleton application.')
                ->addArgument('name', InputArgument::OPTIONAL)
                ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release');
        }

        /**
         * Execute the command.
         *
         * @param  InputInterface  $input
         * @param  OutputInterface  $output
         * @return void
         */
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            if (! class_exists('ZipArchive')) {
                throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
            }

            $this->verifyApplicationDoesntExist(
                $directory = ($input->getArgument('name')) ? getcwd() . '/' . $input->getArgument('name') : getcwd(),
                $output
            );

            $output->writeln('<info>Crafting application...</info>');

            $version = $this->getVersion($input);

            $this->download($zipFile = $this->makeFilename(), $version)
                 ->extract($zipFile, $directory)
                 ->cleanUp($zipFile);

            $composer = $this->findComposer();

            $commands = [
                $composer . ' install --no-scripts',
                $composer . ' run-script post-install-cmd'
            ];

            $process = new Process(implode(' && ', $commands), $directory, null, null, null);

            if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
                $process->setTty(true);
            }

            $process->run(function ($type, $line) use ($output) {
                $output->write($line);
            });

            $output->writeln('<comment>Application ready! Build something amazing.</comment>');
        }

        /**
         * Verify that the application does not already exist.
         *
         * @param  string  $directory
         * @return void
         */
        protected function verifyApplicationDoesntExist($directory, OutputInterface $output)
        {
            if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
                throw new RuntimeException('Application already exists!');
            }
        }

        /**
         * Generate a random temporary filename.
         *
         * @return string
         */
        protected function makeFilename()
        {
            return getcwd() . '/octo_skeleton_' . md5(time() . uniqid()) . '.zip';
        }

        /**
         * Download the temporary Zip to the given file.
         *
         * @param  string  $zipFile
         * @param  string  $version
         * @return $this
         */
        protected function download($zipFile, $version = 'master')
        {
            switch ($version) {
                case 'master':
                    $filename = 'http://github.com/rueduphp/skeleton/archive/master.zip';
                    break;
                case 'develop':
                    $filename = file_get_contents('http://www.rueduphp.com/dev.txt');
                    break;
            }

            $response = (new Client)->get($filename);

            file_put_contents($zipFile, $response->getBody());

            return $this;
        }

        /**
         * Extract the zip file into the given directory.
         *
         * @param  string  $zipFile
         * @param  string  $directory
         * @return $this
         */
        protected function extract($zipFile, $directory)
        {
            $archive = new ZipArchive;

            $archive->open($zipFile);

            $archive->extractTo($directory);

            $archive->close();

            $source = $directory . DIRECTORY_SEPARATOR . 'skeleton-master';

            $this->copy($source, $directory);

            return $this;
        }

        protected function copy($src, $dst)
        {
            $dir = opendir($src);

            if (!is_dir($dst)) mkdir($dst);

            while(false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                        mkdir($dst . DIRECTORY_SEPARATOR . $file);
                        $this->copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                    } else {
                        copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                        unlink($src . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }

            closedir($dir);

            rmdir($src);
        }

        /**
         * Clean-up the Zip file.
         *
         * @param  string  $zipFile
         * @return $this
         */
        protected function cleanUp($zipFile)
        {
            @chmod($zipFile, 0777);

            @unlink($zipFile);

            return $this;
        }

        /**
         * Get the version that should be downloaded.
         *
         * @param  \Symfony\Component\Console\Input\InputInterface  $input
         * @return string
         */
        protected function getVersion($input)
        {
            if ($input->getOption('dev')) {
                return 'develop';
            }

            return 'master';
        }

        /**
         * Get the composer command for the environment.
         *
         * @return string
         */
        protected function findComposer()
        {
            if (file_exists(getcwd() . '/composer.phar')) {
                return '"' . PHP_BINARY . '" composer.phar';
            }

            return 'composer';
        }
    }
