<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Telegram\Bot\Api;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Response;
use Slim\Factory\AppFactory;
use Psr\Container\ContainerInterface;
use Telegram\Bot\Exceptions\TelegramSDKException;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use PDO;
use GuzzleHttp\Client;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    Api::class => function (ContainerInterface $container) {
        return new Api($_ENV['TG_TOKEN']);
    },

    PDO::class => function (ContainerInterface $container) {
        $host = $_ENV['DB_HOST'];
        $dbname =  $_ENV['DB_DATABASE'];
        $username = $_ENV['DB_USERNAME'];
        $password = $_ENV['DB_PASSWORD'];
        $dsn = "mysql:host=$host;dbname=$dbname";
        return new PDO($dsn, $username, $password);
    },
]);
$container = $containerBuilder->build();

// $app->addErrorMiddleware(false, false, false);

$app->get('/', function (ServerRequest $request, Response $response) {

    return $response->withStatus(200);
});

$app->post('/webhook', function (ServerRequest $request, Response $response) use ($container) {
    $telegram = $container->get(Api::class);
    $client = new Client();
    $updates = $telegram->getWebhookUpdate();
    $chatId = $updates->getMessage()->chat->id;
    if ($updates->getMessage()->text === '/start') {
        $text = "Hi! " . $updates->getMessage()->from->first_name . "\n/InProgress - create list InProgress\n/Done - create list Done";
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => $text]);

        $pdo = $container->get(PDO::class);
        $stmt = $pdo->prepare("INSERT INTO users (tg_name, tg_id) VALUES (:tg_name, :tg_id)");
        $stmt->bindParam(':tg_name', $updates->getMessage()->from->username);
        $stmt->bindParam(':tg_id', $updates->getMessage()->from->id);
        $stmt->execute();
    }
    if ($updates->getMessage()->text === '/InProgress') {
        $data = [
            'key' => $_ENV['TRELLO_KEY'],
            'token' => $_ENV['TRELLO_TOKEN'],
            'name' => 'InProgress',
            'idBoard' => $_ENV['TRELLO_BOARD'],
        ];
        $responseFromTrello = $client->request('POST', 'https://api.trello.com/1/lists', [
            'form_params' => $data
        ]);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "Created InProgress"]);
        return $response->withStatus($responseFromTrello->getStatusCode());
    }
    if ($updates->getMessage()->text === '/Done') {
        $data = [
            'key' => $_ENV['TRELLO_KEY'],
            'token' => $_ENV['TRELLO_TOKEN'],
            'name' => 'Done',
            'idBoard' => $_ENV['TRELLO_BOARD'],
        ];
        $responseFromTrello = $client->request('POST', 'https://api.trello.com/1/lists', [
            'form_params' => $data
        ]);
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => "Created Done"]);
        return $response->withStatus($responseFromTrello->getStatusCode());
    }

    // if ($updates->getMessage()->text === '/assoc') {
    //     $data = [
    //         'key' => $_ENV['TRELLO_KEY'],
    //         'token' => $_ENV['TRELLO_TOKEN'],
    //         'idBoard' => $_ENV['TRELLO_BOARD'],
    //     ];
    //     $responseFromTrello = $client->request(
    //         'GET', "https://api.trello.com/1/boards/{$_ENV['TRELLO_BOARD']}/members?key={$_ENV['TRELLO_KEY']}&token={$_ENV['TRELLO_TOKEN']}"
    //     );
    //     $responseBody = $responseFromTrello->getBody()->getContents();
    //     $members = json_decode($responseBody, true);

    //     $output = '';
    //     foreach ($members as $member) {
    //         $output .= "id: {$member['id']}, Полное имя: {$member['fullName']}, Имя пользователя: {$member['username']}\n";
    //     }
    //     $telegram->sendMessage(['chat_id' => $chatId, 'text' => $output]);
    //     return $response->withStatus($responseFromTrello->getStatusCode());
    // }
    return $response->withStatus(200);
});



$app->post('/trello-webhook', function (ServerRequest $request, Response $response) use ($container) {
    $requestData = json_decode($request->getBody(), true);
    if (isset($requestData['action'])) {
        if ($requestData['action']['type'] === 'updateCard' && isset($requestData['action']['data']['listAfter'])) {
            $cardName = $requestData['action']['data']['card']['name'];
            $listNameAfter = $requestData['action']['data']['listAfter']['name'];
            $telegram = $container->get(Api::class);
            $chatId = $_ENV['TG_CHAT_ID'];
            $message = "The card '{$cardName}' has been moved to the list '{$listNameAfter}' in Trello.";
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
            ]);
        }
    }
    return $response->withStatus(200);
});

$app->run();
