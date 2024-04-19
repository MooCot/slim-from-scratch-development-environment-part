<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Telegram\Bot\Api;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Response;
use Slim\Factory\AppFactory;
use DI\Container;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;



$container = new Container();
$container->set('app', function () {
    return AppFactory::create();
});

$app = $container->get('app');

// $app->addErrorMiddleware(false, false, false);

$app->get('/', function (ServerRequest $request, Response $response) {
    $response->getBody()->write('Hello from Slim 43 request handler');
    // $telegramBotToken = '7042060928:AAHZN6DJASOdX_7GOLoyDuKNQLw5sX9WMiw';
    // $client = new GuzzleHttp\Client();
    // $client->post(
    //     "https://api.telegram.org/bot$telegramBotToken/sendMessage",
    //     [
    //         'json' => [
    //             'chat_id' => "340252537",
    //             'text' => "340252537"
    //         ]
    //     ]
    // );
    return $response->withStatus(200);
});

$app->post('/webhook', function (ServerRequest $request, Response $response) {
    $data = $request->getParsedBody();
    $telegram = new Api('7042060928:AAHZN6DJASOdX_7GOLoyDuKNQLw5sX9WMiw');
    $updates = $telegram->getWebhookUpdate();
    if ($updates->getMessage()->text === '/start') {
        $chatId = $updates->getMessage()->chat->id;
        $text = "Привет! " . $updates->getMessage()->chat->first_name;
        $telegram->sendMessage(['chat_id' => $chatId, 'text' => $text]); 
    }

    return $response->withStatus(200);
});



$app->post('/trello-webhook', function (ServerRequest $request, Response $response) {
    $requestData = json_decode($request->getBody(), true);
    if (isset($requestData['action'])) {
        if ($requestData['action']['type'] === 'updateCard' && isset($requestData['action']['data']['listAfter'])) {
            $cardName = $requestData['action']['data']['card']['name'];
            $listNameAfter = $requestData['action']['data']['listAfter']['name'];
            $telegram = new Api('7042060928:AAHZN6DJASOdX_7GOLoyDuKNQLw5sX9WMiw');
            $chatId = '-4126034179';
            $message = "Карточка '{$cardName}' была перемещена в список '{$listNameAfter}' в Trello.";
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $message,
            ]);
        }
    }
    return $response->withStatus(200);
});

$app->run();
