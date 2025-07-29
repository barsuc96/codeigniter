<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Response;

class Api extends BaseController
{
    private string $redisHost = "";
    private string $redisPort = "";

    public function __construct()
    {
        $this->redisPort = env("redis.port", 6379);
        $this->redisHost = env("redisHost", "redis");
    }

    public function index(): string
    {
        return view("welcome_message");
    }

    public function coasters(): Response
    {
        $post = $this->request->getJSON(true);
        $redis = new \Redis();
        $redis->connect($this->redisHost, $this->redisPort);

        $nazwa = $post['nazwa'] ?? null;
        if (!$nazwa) {
            log_message('error', '[API] Brakuje nazwy kolejki przy próbie dodania.');
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Brakuje nazwy kolejki']);
        }

        if (
            !isset($post["liczba_personelu"]) ||
            $post["liczba_personelu"] <= 1 || 
            !isset($post["godziny_od"]) ||
            !isset($post["godziny_do"])
        ) {
            log_message('error', "[API] Brakuje wymaganych danych przy dodawaniu kolejki");
            return $this->response->setStatusCode(400)->setJSON(["error" => "Brakuje wymaganych danych"]);
        }

        $idKey = "coaster_id:next";
        $coasterId = $redis->incr($idKey);
        $key = "coasters:$coasterId";

        $post['nazwa'] = $nazwa;
        $encoded = json_encode($post);

        $redis->multi()
            ->set($key, $encoded)
            ->set("coaster_nazwa:$nazwa", $key)
            ->exec();

        log_message('info', "[API] Dodano nową kolejkę $nazwa (ID: $coasterId): $encoded");

        return $this->response->setJSON([
            "status" => "ok",
            "zapisano" => $encoded,
            "id" => $coasterId,
        ]);
    }

    public function newWagons(string $coasterId): Response
    {
        $data = $this->request->getJSON(true);
        $redis = new \Redis();
        $redis->connect($this->redisHost, $this->redisPort);

        if (!isset($data["ilosc_miejsc"], $data["predkosc_wagonu"])) {
            log_message('error', "[API] Brakuje wymaganych danych wagonu dla ID $coasterId");
            return $this->response->setStatusCode(400)->setJSON(["error" => "Brakuje wymaganych danych wagonu"]);
        }

        $key = "coasters:$coasterId";
        if (!$redis->exists($key)) {
            log_message('error', "[API] Próba dodania wagonu do nieistniejącej kolejki: $coasterId");
            return $this->response->setStatusCode(404)->setJSON(["error" => "Nie znaleziono kolejki o ID '$coasterId'"]);
        }

        $wagonId = $redis->incr("coaster:$coasterId:next_wagon_id");
        $wagonKey = "coaster:$coasterId:wagon:$wagonId";

        $redis->multi()
            ->set($wagonKey, json_encode($data))
            ->sAdd("coaster:$coasterId:wagons", $wagonId)
            ->exec();

        log_message('info', "[API] Dodano wagon $wagonId do kolejki $coasterId: " . json_encode($data));

        return $this->response->setJSON([
            "status" => "ok",
            "zapisano" => $redis->sMembers("coaster:$coasterId:wagons"),
        ]);
    }

    public function deleteWagons(string $coasterId, string $wagonId): Response
    {
        $redis = new \Redis();
        $redis->connect($this->redisHost, $this->redisPort);

        $redis->multi()
            ->del("coaster:$coasterId:wagon:$wagonId")
            ->sRem("coaster:$coasterId:wagons", $wagonId)
            ->exec();

        log_message('info', "[API] Usunięto wagon $wagonId z kolejki $coasterId");

        return $this->response->setJSON([
            "status" => "deleted",
            "wagonId" => $wagonId,
        ]);
    }

    public function updateCoaster(string $coasterId): Response
    {
        $data = $this->request->getJSON(true);
        $redis = new \Redis();
        $redis->connect($this->redisHost, $this->redisPort);

        $key = "coasters:$coasterId";
        if (!$redis->exists($key)) {
            log_message('error', "[API] Próba aktualizacji nieistniejącej kolejki: $coasterId");
            return $this->response->setStatusCode(404)->setJSON([
                "status" => "error",
                "message" => "Kolejka nie istnieje",
            ]);
        }

        $oldData = json_decode($redis->get($key), true);
        foreach ($data as $keyItem => $value) {
            $oldData[$keyItem] = $value;
        }

        $encoded = json_encode($oldData);
        $redis->set($key, $encoded);

        log_message('info', "[API] Zaktualizowano dane kolejki $coasterId: $encoded");

        return $this->response->setJSON([
            "status" => "Zaktualizowano",
            "Update" => $encoded,
        ]);
    }
}
