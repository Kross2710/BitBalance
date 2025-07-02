<?php
function fetch_weather(float $lat, float $lon): ?array
{
    $apiKey = '25328871c089440795391842252206';
    $url = sprintf(
        'https://api.weatherapi.com/v1/forecast.json?key=%s&q=%f,%f&days=7&aqi=no&alerts=no',
        $apiKey, $lat, $lon
    );
    $json = @file_get_contents($url);

    if ($json !== false) {
        $data = json_decode($json, true);

        return [
            'provider' => 'weatherapi',
            /* current section */
            'current'  => [
                'temp'  => $data['current']['temp_c']  ?? null,
                'icon'  => $data['current']['condition']['icon'] ?? '',
                'text'  => $data['current']['condition']['text'] ?? ''
            ],
            /* 7-day forecast arrays */
            'daily' => array_map(function ($d) {
                return [
                    'date'  => $d['date'],
                    'max'   => $d['day']['maxtemp_c'],
                    'min'   => $d['day']['mintemp_c']
                ];
            }, $data['forecast']['forecastday'])
        ];
    }
    // If the API call fails or returns no data, return null
    return null;
}