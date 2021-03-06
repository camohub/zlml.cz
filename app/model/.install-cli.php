<?php
$security = <<<NEON
#
# SECURITY WARNING: it is CRITICAL that this file & directory are NOT accessible directly via a web browser!
#
# If you don't protect this directory from direct web access, anybody will be able to see your passwords.
# http://nette.org/security-warning
#\n\n
NEON;

try {
	$options = getopt('u:n:p:d:');
	$driver = isset($options['d']) && in_array($options['d'], ['mysql', 'pgsql']) ? 'pdo_' . $options['d'] : 'pdo_mysql';
	file_put_contents(__DIR__ . '/../config/config.local.neon', $security . \Nette\Neon\Neon::encode(['doctrine' => [
			'host' => '127.0.0.1',
			'user' => $options['u'],
			'password' => isset($options['p']) ? $options['p'] : '',
			'dbname' => $options['n'],
			'driver' => $driver,
		]]));

	$config = new \Nette\Configurator();
	$container = $config->setTempDirectory(__DIR__ . '/../../temp')
		->addParameters([
			'appDir' => __DIR__ . '/../../app',
		])->addConfig(__DIR__ . '/../config/config.neon')
		->addConfig(__DIR__ . '/../config/config.local.neon')
		->createContainer();

	/** @var \Kdyby\Doctrine\EntityManager $em */
	$em = $container->getByType('\Kdyby\Doctrine\EntityManager');

	/** @var \Kdyby\Doctrine\Connection $conn */
	$conn = $container->getByType('\Kdyby\Doctrine\Connection');

	$schemaTool = new Doctrine\ORM\Tools\SchemaTool($em);
	$schemaTool->updateSchema($em->getMetadataFactory()->getAllMetadata());

	$admin = new \Entity\User;
	$admin->username = 'admin';
	$admin->password = \Nette\Security\Passwords::hash('admin');
	$admin->role = "admin";
	$em->persist($admin);

	$demo = new \Entity\User;
	$demo->username = "demo";
	$demo->password = \Nette\Security\Passwords::hash("demo");
	$demo->role = "demo";
	$em->persist($demo);

	if ($driver == 'pdo_mysql') {
		$settingSql = file_get_contents(__DIR__ . '/../../sql/settings-mysql.sql');
	} else {
		$settingSql = file_get_contents(__DIR__ . '/../../sql/settings-pgsql.sql');
	}
	/** @var PDOStatement $setting */
	$setting = $conn->prepare($settingSql);
	$setting->execute();

	//Doctrine fulltext workaround:
	if ($driver == 'pdo_mysql') {
		$workaroundSql = file_get_contents(__DIR__ . '/../../sql/fulltext-workaround-mysql.sql');
		/** @var PDOStatement $workaround */
		$workaround = $conn->prepare($workaroundSql);
		$workaround->execute();
	}

	$post = new \Entity\Post;
	$title = 'Welcome to your new blog!';
	$post->title = $title;
	$post->slug = Nette\Utils\Strings::webalize($title);
	$post->body = 'The installation was successful. Yaay! (-:';
	$post->date = new \DateTime;
	$post->publish_date = new \DateTime;
	$em->persist($post);

	$em->flush();

	echo "\nThe installation was successful!\n";
	return 0; // zero return code means everything is ok
} catch (\Exception $exc) {
	file_put_contents(__DIR__ . '/../config/config.local.neon', $security);
	echo "\nERROR (#" . $exc->getCode() . ", on line: " . $exc->getLine() . "): " . $exc->getMessage() . "\n";
	debug_print_backtrace();
	return 1; // non-zero return code means error
}
