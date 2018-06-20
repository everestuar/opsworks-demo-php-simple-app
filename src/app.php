<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db-connect.php';

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;

// Setup the application
$app = new Application();
$app->register(new TwigServiceProvider, array(
    'twig.path' => __DIR__ . '/templates',
));

// Setup the database
$app['db.table'] = DB_TABLE;
$app['db.dsn'] = 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST;
$app['db'] = $app->share(function ($app) {
    return new PDO($app['db.dsn'], DB_USER, DB_PASSWORD);
});

// Handle the index page
$app->match('/', function () use ($app) {
    $query = $app['db']->prepare("SELECT message, author FROM {$app['db.table']}");
    $thoughts = $query->execute() ? $query->fetchAll(PDO::FETCH_ASSOC) : array();

    return $app['twig']->render('index.twig', array(
        'title'    => 'Tu Opinión',
        'thoughts' => $thoughts,
    ));
});

// Handle the add page
$app->match('/add', function (Request $request) use ($app) {
    $alert = null;
    // If the form was submitted, process the input
    if ('POST' == $request->getMethod()) {
        try {
            // Make sure the photo was uploaded without error
            $message = $request->request->get('thoughtMessage');
            $author = $request->request->get('thoughtAuthor');
            if ($message && $author && strlen($author) < 64) {
                // Save the thought record to the database
                $sql = "INSERT INTO {$app['db.table']} (message, author) VALUES (:message, :author)";
                $query = $app['db']->prepare($sql);
                $data = array(
                    ':message' => $message,
                    ':author'  => $author,
                );
                if (!$query->execute($data)) {
                    throw new \RuntimeException('Error al guardar tu opinión en la Base de Datos.');
                }
            } else {
                throw new \InvalidArgumentException('Lo sentimos, el formato no es válido.');
            }

            // Display a success message
            $alert = array('type' => 'success', 'message' => 'Gracias por compartir tu opinión.');
        } catch (Exception $e) {
            // Display an error message
            $alert = array('type' => 'error', 'message' => $e->getMessage());
        }
    }

    return $app['twig']->render('add.twig', array(
        'title' => '¡Comparte tu opinión!',
        'alert' => $alert,
    ));
});

$app->run();
