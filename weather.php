<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function fetchWeather(string $date): array {
  $lat = KUNOHE_LAT;
  $lon = KUNOHE_LON;

  $qs = http_build_query([
    'latitude' => $lat,
    'longitude' => $lon,
    'daily' => 'weathercode,temperature_2m_max',
    'timezone' => 'Asia/Tokyo',
    'start_date' => $date,
    'end_date' => $date,
  ]);

  $url = "https://api.open-meteo.com/v1/forecast?$qs";
  $json = @file_get_contents($url);
  if (!$json) return ['weather_code'=>null, 'temp_c'=>null];

  $data = json_decode($json, true);
  $code = $data['daily']['weathercode'][0] ?? null;
  $temp = $data['daily']['temperature_2m_max'][0] ?? null;

  return [
    'weather_code' => is_null($code) ? null : (string)$code,
    'temp_c' => is_null($temp) ? null : (float)$temp,
  ];
}
