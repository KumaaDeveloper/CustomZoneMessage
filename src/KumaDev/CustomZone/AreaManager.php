<?php

namespace KumaDev\CustomZone;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\scheduler\ClosureTask;

class AreaManager implements Listener {

    private $plugin;
    private $config;
    private $areas;
    private $lastPopupTime = [];
    private $entryTime = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . "area.yml", Config::YAML);
        $this->areas = $this->config->get("zones", []);
        
        // Schedule a repeating task to check all players in zones
        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            $this->checkPlayersInZones();
        }), 20); // Runs every second (20 ticks)
    }

    public function createArea(string $name, Vector3 $pos1, Vector3 $pos2): void {
        $this->areas[$name] = [
            "pos1" => ["x" => $pos1->getX(), "y" => $pos1->getY(), "z" => $pos1->getZ()],
            "pos2" => ["x" => $pos2->getX(), "y" => $pos2->getY(), "z" => $pos2->getZ()],
            "text" => "",
            "duration" => 5 // default duration in seconds
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

    public function resetAreaText(string $name): bool {
        if (isset($this->areas[$name])) {
            if (empty($this->areas[$name]["text"])) {
                return false;
            }
            $this->areas[$name]["text"] = "";
            $this->saveAreas();
            return true;
        }
        return false;
    }

    public function editAreaDuration(string $name, int $duration): bool {
        if (isset($this->areas[$name])) {
            if (empty($this->areas[$name]["text"])) {
                return false;
            }
            $this->areas[$name]["duration"] = $duration;
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
        $this->checkPlayerInZones($event->getPlayer());
    }

    private function checkPlayerInZones(Player $player): void {
        $pos = $player->getPosition();
        $playerName = $player->getName();
        $currentTime = time();

        foreach ($this->areas as $name => $area) {
            $pos1 = new Vector3($area["pos1"]["x"], $area["pos1"]["y"], $area["pos1"]["z"]);
            $pos2 = new Vector3($area["pos2"]["x"], $area["pos2"]["y"], $area["pos2"]["z"]);

            if ($this->isWithinArea($pos, $pos1, $pos2)) {
                $text = $area["text"];
                $duration = $area["duration"];

                if (!isset($this->entryTime[$playerName][$name])) {
                    $this->entryTime[$playerName][$name] = $currentTime;
                    $player->sendTip($text);
                    $this->lastPopupTime[$playerName][$name] = $currentTime;
                } elseif ($currentTime - $this->entryTime[$playerName][$name] < $duration) {
                    if ($currentTime - $this->lastPopupTime[$playerName][$name] >= 1) {
                        $player->sendTip($text);
                        $this->lastPopupTime[$playerName][$name] = $currentTime;
                    }
                }
            } else {
                if (isset($this->entryTime[$playerName][$name])) {
                    unset($this->entryTime[$playerName][$name]);
                    unset($this->lastPopupTime[$playerName][$name]);
                }
            }
        }
    }

    private function checkPlayersInZones(): void {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $this->checkPlayerInZones($player);
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
