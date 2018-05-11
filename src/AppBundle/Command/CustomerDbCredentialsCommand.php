<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Script written to maintain our customer db credentials file, and thus our multi-tenant system, via convenient symfony
 * command. We can use this when removing customers, or when creating them. This script should be included in whatever
 * other scripts run when creating / removing customer folders so we are able to maintain our credentials file.
 */
class CustomerDbCredentialsCommand extends ContainerAwareCommand
{
	/**
	 * Contains the customer database credentials file name.
	 *
	 * @var string
	 */
	const CREDENTIALS_FILE = 'customer_db_credentials.yml';

	/**
	 * Contains the default database host. Loads from parameters.yml.
	 *
	 * @var string
	 */
	protected $defaultHost;

	/**
	 * Contains the default database port. Loads from parameters.yml.
	 *
	 * @var int
	 */
	protected $defaultPort;

	/**
	 * Contains all the customer database credentials, with the folder as a key.
	 *
	 * @var array
	 */
	protected $customerDbCredentials;

	/**
	 * The constructor loads the EntityManagerMapperService to get our default database credentials, and the current
	 * customer database credentials.
	 *
	 * @param string     $host
	 * @param int|string $port
	 * @param array      $customerDbCredentials
	 */
	public function __construct($host, $port, $customerDbCredentials)
	{
		$this->defaultHost = $host;
		$this->defaultPort = (int) $port;
		$this->customerDbCredentials = $customerDbCredentials;
		parent::__construct();
	}

	protected function configure()
	{
		$this
			->setName('cdb:credentials')
			->setDescription('Add and remove customer database credentials from the yaml configuration file.')
			->setHelp('Use this script to add and remove customer database credentials for specific folders on the CompanyX 2 servers.
<fg=red;options=bold>Automatically writes configuration yaml files, use with care.</>')
			->addArgument('action', InputArgument::REQUIRED,
				'Action can be either "add", "remove", "info". The "add" command automatically updates existing credentials.')
			->addArgument('folder', InputArgument::REQUIRED, 'The credentials given are for this folder.')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Username of the customer database.')
			->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Password of the customer database.')
			->addOption('database', 'N', InputOption::VALUE_REQUIRED, 'Database name of the customer.')
			->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'Database host, defaults to the default host.',
				$this->defaultHost)
			->addOption('port', 'P', InputOption::VALUE_REQUIRED,
				'Database port, defaults to the default database port.', $this->defaultPort);
	}

	/**
	 * Switches between actions, or gives an informative message if an invalid action is given.
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$action = $input->getArgument('action');

		switch ($action) {
			case 'remove':
				$this->remove($input, $output);
				break;
			case 'add':
				$this->add($input, $output);
				break;
			case 'info':
				$this->info($input, $output);
				break;
			default:
				$output->writeln('<question>No valid action given. Either "add", "remove", or "info".</question>');
				break;
		}
	}

	/**
	 * Adds the folder given in the command argument to the customer db credentials file.
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function add(InputInterface $input, OutputInterface $output)
	{
		$folder = $input->getArgument('folder');
		if ($input->getOption('user') && $input->getOption('password') && $input->getOption('database')) {
			$this->customerDbCredentials[$folder] = [
				'database_host' => addslashes($input->getOption('host')),
				'database_port' => (int) addslashes($input->getOption('port')),
				'database_user' => addslashes($input->getOption('user')),
				'database_password' => addslashes($input->getOption('password')),
				'database_name' => addslashes($input->getOption('database')),
			];
			$output->writeln("<info>Customer {$folder} added.</info>");
			$this->saveYaml($output);
		} else {
			$output->writeln('<error>"user" (-u), "password" (-p), and "database" (-N) are required options when adding credentials.</error>');
		}
	}

	/**
	 * Removes the folder given in the command argument from the customer db credentials file.
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function remove(InputInterface $input, OutputInterface $output)
	{
		$folder = $input->getArgument('folder');
		if (in_array($folder, array_keys($this->customerDbCredentials))) {
			$output->writeln(var_export($this->customerDbCredentials[$folder], true));
			$output->writeln("<info>Customer {$folder} found, credentials removed.</info>");
			unset($this->customerDbCredentials[$folder]);
			$this->saveYaml($output);
		} else {
			$output->writeln('<error>Customer folder not found, no credentials removed.</error>');
		}
	}

	/**
	 * Exports the array of the given folder in the command argument if found.
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function info(InputInterface $input, OutputInterface $output)
	{
		$folder = $input->getArgument('folder');
		if (in_array($folder, array_keys($this->customerDbCredentials))) {
			$output->writeln("<info>Customer {$folder} found, credentials printed below.</info>");
			$output->writeln(var_export($this->customerDbCredentials[$folder], true));
		} else {
			$output->writeln('<error>Customer folder not found, no database credentials known.</error>');
		}
	}

	/**
	 * Writes everything to the customer db credentials yaml file.
	 *
	 * @param OutputInterface $output
	 */
	protected function saveYaml(OutputInterface $output)
	{
		$output->write('Saving yaml file... ');

		$yaml = Yaml::dump([
			'parameters' => [
				'customer_db_credentials' => (array) $this->customerDbCredentials,
			],
		], 3);

		// User file locator so we know for sure the file exists, and thus not print out success when we are writing to
		// a faulty location when the file has been moved.
		$locator = new FileLocator($this->getContainer()->get('kernel')->getRootDir().'/config');
		$file = $locator->locate(self::CREDENTIALS_FILE);
		file_put_contents($file, $yaml);

		$output->writeln('<info>success!</info>');
	}
}
