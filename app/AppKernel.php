<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Yaml\Yaml;

class AppKernel extends Kernel
{
	public function __construct($environment, $debug)
	{
		$parameters = Yaml::parse(file_get_contents($this->getRootDir().'/config/parameters.yml'))['parameters'];
		date_default_timezone_set($parameters['default_timezone']);
		parent::__construct($environment, $debug);
	}

	public function registerBundles()
	{
		$bundles = [
			/* Default bundles */
			// Base framework
			new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
			// Security / authentication configurator
			new Symfony\Bundle\SecurityBundle\SecurityBundle(),
			// HTML templating engine
			new Symfony\Bundle\TwigBundle\TwigBundle(),
			// PHP logger
			new Symfony\Bundle\MonologBundle\MonologBundle(),
			// Mailer
			new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
			// ORM framework
			new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
			// Doctrine Cache Bundle
			new Doctrine\Bundle\DoctrineCacheBundle\DoctrineCacheBundle(),
			//MVC framework
			new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
			/* Additional bundles */
			// Main application bundle
			new AppBundle\AppBundle(),
			// API doc generator
			new Nelmio\ApiDocBundle\NelmioApiDocBundle(),
			// Doctrine extension
			new Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle(),
			// CORS configurator
			new Nelmio\CorsBundle\NelmioCorsBundle(),
			// JWT Authentication
			new Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle(),
			// JWT Refresh Token
			new Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle(),
			// Knp PaginatorBundle
			new Knp\Bundle\PaginatorBundle\KnpPaginatorBundle(),
			// FOS RestBundle
			new FOS\RestBundle\FOSRestBundle(),
			// Noxlogic RateLimitBundle
			new Noxlogic\RateLimitBundle\NoxlogicRateLimitBundle(),
		];

		//Dev and test environment configuration bundles
		if (in_array($this->getEnvironment(), ['dev', 'test', 'expired_token_test', 'rate_limit_test'], true)) {
			$bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
			$bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
			$bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();

			if ('dev' === $this->getEnvironment()) {
				$bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
				$bundles[] = new Symfony\Bundle\WebServerBundle\WebServerBundle();
			}
		}

		return $bundles;
	}

	public function getRootDir()
	{
		return __DIR__;
	}

	public function getCacheDir()
	{
		return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
	}

	public function getLogDir()
	{
		return dirname(__DIR__).'/var/logs';
	}

	public function registerContainerConfiguration(LoaderInterface $loader)
	{
		$loader->load(function (ContainerBuilder $container) {
			$container->setParameter('container.autowiring.strict_mode', true);
			$container->setParameter('container.dumper.inline_class_loader', true);

			$container->addObjectResource($this);
		});
		$loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
	}
}
