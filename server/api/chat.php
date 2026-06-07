<?php

set_error_handler(function ($s, $m, $f, $l) {
    if (!(error_reporting() & $s)) return false;
    throw new ErrorException($m, 0, $s, $f, $l);
});
set_exception_handler(function ($e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
});

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('html_errors', '0');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 获取请求体
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is empty']);
    exit;
}

// 从服务器环境变量读取 API Key，不要把密钥写在代码里
$api_key = getenv('DEEPSEEK_API_KEY') ?: ($_SERVER['DEEPSEEK_API_KEY'] ?? '');
$api_url = getenv('DEEPSEEK_API_URL') ?: 'https://api.deepseek.com/v1/chat/completions';
$model = getenv('DEEPSEEK_MODEL') ?: 'deepseek-chat';
$appDebug = (getenv('APP_DEBUG') ?: '0') === '1';

if (empty($api_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'DEEPSEEK_API_KEY is not configured on server']);
    exit;
}

// 可以在这里设置 AI 的人设 prompt
$system_prompt = "你是Mingine的专属AI助理。你说话风格活泼、友好，能解答访问者关于这个网站、关于Mingine的问题。尽量控制回复长度不要过长。";

$data = [
    "model" => $model,
    "messages" => [
        ["role" => "system", "content" => $system_prompt],
        ["role" => "user", "content" => $message]
    ],
    "temperature" => 0.7
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpcode !== 200) {
    http_response_code(502);
    $result = ['reply' => '抱歉，API请求失败，请稍后再试或检查配置。'];
    if ($appDebug) {
        $result['debug'] = $curlErr ?: ('upstream status: ' . $httpcode);
    }
    echo json_encode($result);
    exit;
}

$result = json_decode($response, true);
if (isset($result['choices'][0]['message']['content'])) {
    $reply = $result['choices'][0]['message']['content'];
    echo json_encode(['reply' => $reply]);
} else {
    echo json_encode(['reply' => '响应格式错误。']);
}
