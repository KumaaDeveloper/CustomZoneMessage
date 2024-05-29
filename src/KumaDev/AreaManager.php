<?php

namespace KumaDev;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class AreaManager implements Listener {

    private $plugin;
    private $config;
    private $areas;
    private $popupDuration = 5; // duration in seconds
    private $lastPopupTime = [];
    private $entryTime = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . "area.yml", Config::YAML);
        $this->areas = $this->config->get("zones", []);
    }

    public function createArea(string $name, Vector3 $pos1, Vector3 $pos2): void {
        $this->areas[$name] = [
            "pos1" => ["x" => $pos1->getX(), "y" => $pos1->getY(), "z" => $pos1->getZ()],
            "pos2" => ["x" => $pos2->getX(), "y" => $pos2->getY(), "z" => $pos2->getZ()],
            "text" => ""
        ];
        $this->saveAreas();
    }

    public function listAreas(): array {
        return array_keys($this->areas);
    }

    public function deleteArea(string $name): bool {
        if (isset($this->areas[$name])) {
            unset($this->areas[$name]);
            $this->saveAreas();
            return true;
        }
        return false;
    }

    public function editAreaText(string $name, string $text): bool {
        if (isset($this->areas[$name])) {
            $this->areas[$name]["text"] = $text;
            $this->saveAreas();
            return true;
        }
        return false;
    }

    private function saveAreas(): void {
        $this->config->set("zones", $this->areas);
        $this->config->save();
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $pos = $player->getPosition();
        $playerName = $player->getName();
        $currentTime = time();

        foreach ($this->areas as $name => $area) {
            $pos1 = new Vector3($area["pos1"]["x"], $area["pos1"]["y"], $area["pos1"]["z"]);
            $pos2 = new Vector3($area["pos2"]["x"], $area["pos2"]["y"], $area["pos2"]["z"]);

            if ($this->isWithinArea($pos, $pos1, $pos2)) {
                $text = $area["text"];
                
                if (!isset($this->entryTime[$playerName])) {
                    $this->entryTime[$playerName] = $currentTime;
                }

                if ($currentTime - $this->entryTime[$playerName] <= $this->popupDuration) {
                    if (!isset($this->lastPopupTime[$playerName]) || $currentTime - $this->lastPopupTime[$playerName] >= 1) {
                        $player->sendTip($text);
                        $this->lastPopupTime[$playerName] = $currentTime;
                    }
                }
            } else {
                unset($this->entryTime[$playerName]);
                unset($this->lastPopupTime[$playerName]);
            }
        }
    }

    private function isWithinArea(Vector3 $pos, Vector3 $pos1, Vector3 $pos2): bool {
        $minX = min($pos1->getX(), $pos2->getX());
        $maxX = max($pos1->getX(), $pos2->getX());
        $minY = min($pos1->getY(), $pos2->getY());
        $maxY = max($pos1->getY(), $pos2->getY());
        $minZ = min($pos1->getZ(), $pos2->getZ());
        $maxZ = max($pos1->getZ(), $pos2->getZ());

        return ($pos->getX() >= $minX && $pos->getX() <= $maxX) &&
               ($pos->getY() >= $minY && $pos->getY() <= $maxY) &&
               ($pos->getZ() >= $minZ && $pos->getZ() <= $maxZ);
    }
}
