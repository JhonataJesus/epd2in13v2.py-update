<?php
declare(strict_types=1);

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path ?? '', '/');
$segments = explode('/', ltrim($path, '/'));

$ticker = '';
if (count($segments) >= 3 && $segments[0] === 'products' && $segments[2] === 'candles') {
    $ticker = $segments[1];
} elseif (!empty($_GET['ticker'])) {
    $ticker = $_GET['ticker'];
}

if ($ticker === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ticker. Use /products/{ticker}/candles']);
    exit;
}

$granularity = isset($_GET['granularity']) ? (int)$_GET['granularity'] : 900;
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

function parse_time_param($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        return (int)$value;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }
    return $timestamp;
}

$from = parse_time_param($start);
$to = parse_time_param($end);

if ($from === null || $to === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid start/end time.']);
    exit;
}

$resolution_map = [
    60 => '1',
    300 => '5',
    900 => '15',
    1800 => '30',
    3600 => '60',
    86400 => 'D',
];
$resolution = $resolution_map[$granularity] ?? '15';

$token = $_GET['token'] ?? '';
if ($token === '' && isset($_SERVER['HTTP_X_FINNHUB_TOKEN'])) {
    $token = $_SERVER['HTTP_X_FINNHUB_TOKEN'];
}
if ($token === '' && getenv('FINNHUB_TOKEN')) {
    $token = getenv('FINNHUB_TOKEN');
}
if ($token === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Missing Finnhub token.']);
    exit;
}

$query = http_build_query([
    'symbol' => $ticker,
    'resolution' => $resolution,
    'from' => $from,
    'to' => $to,
    'token' => $token,
]);

$url = 'https://finnhub.io/api/v1/stock/candle?' . $query;
$response = @file_get_contents($url);
if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch data from Finnhub.']);
    exit;
}

$payload = json_decode($response, true);
if (!is_array($payload) || ($payload['s'] ?? '') !== 'ok') {
    echo json_encode([]);
    exit;
}

$times = $payload['t'] ?? [];
$lows = $payload['l'] ?? [];
$highs = $payload['h'] ?? [];
$opens = $payload['o'] ?? [];
$closes = $payload['c'] ?? [];
$volumes = $payload['v'] ?? [];

$candles = [];
$count = count($times);
for ($i = 0; $i < $count; $i++) {
    if (!isset($lows[$i], $highs[$i], $opens[$i], $closes[$i], $volumes[$i])) {
        continue;
    }
    $candles[] = [
        (int)$times[$i],
        (float)$lows[$i],
        (float)$highs[$i],
        (float)$opens[$i],
        (float)$closes[$i],
        (float)$volumes[$i],
    ];
}

echo json_encode(array_reverse($candles));
