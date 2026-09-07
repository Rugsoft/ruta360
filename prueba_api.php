<?php
$url = 'https://api.open-meteo.com/v1/forecast'
    . '?latitude=41.3874'
    . '&longitude=2.1686'
    . '&current=temperature_2m,wind_speed_10m';

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$respuesta = curl_exec($curl);
curl_close($curl);

echo $respuesta;
?>
