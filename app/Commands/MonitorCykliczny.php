<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use React\EventLoop\Loop;
use Clue\React\Redis\Factory as RedisFactory;
use React\Promise\PromiseInterface;
use Clue\React\Redis\Client;

class MonitorCykliczny extends BaseCommand
{
    protected $group       = 'Custom';
    protected $name        = 'monitor:run';
    protected $description = 'Uruchamia monitor cykliczny do analizy kolejek z Redis';

    public function run(array $params)
    {
        CLI::write("[Monitor] Tryb cykliczny uruchomiony", 'green');

        $loop = Loop::get();
        $redisFactory = new RedisFactory($loop);

        $redisFactory->createClient('redis://redis:6379')->then(function (Client $client) use ($loop) {
            $loop->addPeriodicTimer(10, function () use ($client) {
                $timeNow = date('Y-m-d H:i:s');
                CLI::write("[$timeNow] Sprawdzanie stanu kolejek...", 'cyan');

                $client->keys('coasters:*')->then(function ($keys) use ($client) {
                    foreach ($keys as $key) {
                        if (!preg_match('/^coasters:(\d+)$/', $key, $m)) {
                            continue;
                        }

                        $coasterId = $m[1];

                        $client->get($key)->then(function ($value) use ($client, $coasterId) {
                            $coaster = json_decode($value, true);
                            if (!$coaster) return;

                            $nazwa = $coaster['nazwa'] ?? "ID $coasterId";
                            $godzinyOd = $coaster['godziny_od'] ?? '00:00';
                            $godzinyDo = $coaster['godziny_do'] ?? '00:00';
                            $liczbaPersonelu = $coaster['liczba_personelu'] ?? 0;
                            $liczbaKlientow = $coaster['liczba_klientow'] ?? 0;

                            $start = date('H:i', strtotime($godzinyOd));
                            $end = date('H:i', strtotime($godzinyDo));
                            $timeNow = date('H:i');

                            if ($start > $timeNow || $end < $timeNow) {
                                $msg = "[$nazwa] (ID: $coasterId) — Kolejka zamknięta";
                                CLI::write($msg, 'yellow');
                                log_message('warning', $msg);
                                return;
                            }

                            $czasDzialania = (strtotime($godzinyDo) - strtotime($godzinyOd)) / 60;

                            $client->sMembers("coaster:$coasterId:wagons")->then(function ($wagonIds) use ($client, $coasterId, $nazwa, $godzinyOd, $godzinyDo, $czasDzialania, $liczbaPersonelu, $liczbaKlientow) {
                                $wagonCount = count($wagonIds);
                                if ($wagonCount === 0) {
                                    $msg = "[$nazwa] (ID: $coasterId) — brak wagonów";
                                    CLI::write($msg, 'red');
                                    log_message('error', $msg);
                                    return;
                                }

                                $wagonPromises = [];
                                foreach ($wagonIds as $wagonId) {
                                    $wagonPromises[] = $client->get("coaster:$coasterId:wagon:$wagonId");
                                }

                                \React\Promise\all($wagonPromises)->then(function ($wagonDataList) use ($wagonCount, $coasterId, $nazwa, $godzinyOd, $godzinyDo, $czasDzialania, $liczbaPersonelu, $liczbaKlientow) {
                                    $totalSeats = 0;
                                    $sumaCzasuPrzejazdu = 0;

                                    foreach ($wagonDataList as $dataJson) {
                                        $wagon = json_decode($dataJson, true);
                                        if (!$wagon || !isset($wagon['ilosc_miejsc'])) continue;

                                        $totalSeats += (int)$wagon['ilosc_miejsc'];
                                        $dlugoscTrasy = $wagon['dlugosc_trasy'] ?? 0;
                                        $predkosc = max(1, $wagon['predkosc'] ?? 1);
                                        $czasPrzejazduMinuty = ($dlugoscTrasy / $predkosc) / 60 + 5;
                                        $sumaCzasuPrzejazdu += $czasPrzejazduMinuty;
                                    }

                                    $sredniCzasPrzejazdu = $wagonCount > 0 ? $sumaCzasuPrzejazdu / $wagonCount : 10;
                                    $przejazdowNaWagon = floor($czasDzialania / $sredniCzasPrzejazdu);
                                    $maxKlientow = $totalSeats * $przejazdowNaWagon;

                                    $wymaganyPersonel = 1 + ($wagonCount * 2);
                                    $problemy = [];

                                    if ($liczbaPersonelu < $wymaganyPersonel) {
                                        $msg = "Brakuje " . ($wymaganyPersonel - $liczbaPersonelu) . " pracowników";
                                        $problemy[] = $msg;
                                        log_message('notice', "[$nazwa] (ID: $coasterId) $msg");
                                    }

                                    if ($maxKlientow < $liczbaKlientow) {
                                        $msg = "Brakuje miejsc (obsługa tylko $maxKlientow z $liczbaKlientow klientów)";
                                        $problemy[] = $msg;
                                        log_message('notice', "[$nazwa] (ID: $coasterId) $msg");
                                    }

                                    CLI::write("[$nazwa] (ID: $coasterId)", 'white');
                                    CLI::write("Godziny: $godzinyOd - $godzinyDo");
                                    CLI::write("Wagony: $wagonCount, Miejsca łącznie: $totalSeats");
                                    CLI::write("Personel: $liczbaPersonelu/$wymaganyPersonel");
                                    CLI::write("Klienci dziennie: $liczbaKlientow");

                                    if (!empty($problemy)) {
                                        $msg = "Problemy: " . implode(', ', $problemy);
                                        CLI::write($msg, 'red');
                                        log_message('warning', "[$nazwa] (ID: $coasterId) $msg");
                                    } else {
                                        CLI::write("Status: OK", 'green');
                                        log_message('info', "[$nazwa] (ID: $coasterId) — wszystko OK");
                                    }

                                    CLI::newLine();
                                });
                            });
                        });
                    }
                });
            });
        });

        $loop->run();
    }
}
