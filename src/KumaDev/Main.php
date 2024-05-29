<?php

namespace KumaDev;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;

class Main extends PluginBase implements Listener {

    private $areaManager;
    private $pos1;
    private $pos2;
    private $config;
    private $settingPos1 = [];
    private $settingPos2 = [];

    public function onEnable(): void {
        $this->areaManager = new AreaManager($this);
        $this->getServer()->getPluginManager()->registerEvents($this->areaManager, $this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Load configuration
        $this->saveResource('config.yml');
        $this->config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
            return false;
        }

        if ($command->getName() === "czm" || $command->getName() === "customzonemessage") {
            if (count($args) < 1) {
                $sender->sendMessage(TextFormat::GREEN . $this->config->get("message_customzone_usage"));
                return true;
            }

            switch ($args[0]) {
                case "pos1":
                    $this->settingPos1[$sender->getName()] = true;
                    $sender->sendMessage(TextFormat::GREEN . $this->config->get("message_set_pos1_mode"));
                    break;

                case "pos2":
                    $this->settingPos2[$sender->getName()] = true;
                    $sender->sendMessage(TextFormat::GREEN . $this->config->get("message_set_pos2_mode"));
                    break;

                case "create":
                    if (count($args) < 2 || $this->pos1 === null || $this->pos2 === null) {
                        $sender->sendMessage(TextFormat::RED . $this->config->get("message_create_usage"));
                        break;
                    }
                    if ($this->pos1->equals($this->pos2)) {
                        $sender->sendMessage(TextFormat::RED . $this->config->get("message_pos_same"));
                        break;
                    }
                    $name = $args[1];
                    $this->areaManager->createArea($name, $this->pos1, $this->pos2);
                    $sender->sendMessage(TextFormat::GREEN . str_replace('$name', $name, $this->config->get("message_zone_created")));
                    $this->pos1 = null;
                    $this->pos2 = null;
                    break;

                case "list":
                    $zones = $this->areaManager->listAreas();
                    $sender->sendMessage(TextFormat::YELLOW . "Zones: " . implode(", ", $zones));
                    break;

                case "delete":
                    if (count($args) < 2) {
                        $sender->sendMessage(TextFormat::RED . $this->config->get("message_delete_usage"));
                        break;
                    }
                    $name = $args[1];
                    if ($this->areaManager->deleteArea($name)) {
                        $sender->sendMessage(TextFormat::GREEN . str_replace('$name', $name, $this->config->get("message_zone_deleted")));
                    } else {
                        $sender->sendMessage(TextFormat::RED . str_replace('$name', $name, $this->config->get("message_zone_not_found")));
                    }
                    break;

                case "edit":
                    if (count($args) < 3) {
                        $sender->sendMessage(TextFormat::RED . $this->config->get("message_edit_usage"));
                        break;
                    }
                    $name = $args[1];
                    $text = implode(" ", array_slice($args, 2));
                    if ($this->areaManager->editAreaText($name, $text)) {
                        $sender->sendMessage(TextFormat::GREEN . str_replace('$name', $name, $this->config->get("message_zone_text_updated")));
                    } else {
                        $sender->sendMessage(TextFormat::RED . str_replace('$name', $name, $this->config->get("message_zone_not_found")));
                    }
                    break;

                default:
                    $sender->sendMessage(TextFormat::GREEN . $this->config->get("message_customzone_usage"));
                    return true;
            }

            return true;
        }

        return false;
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if (isset($this->settingPos1[$playerName])) {
            $this->pos1 = $event->getBlock()->getPosition();
            unset($this->settingPos1[$playerName]);
            $player->sendMessage(TextFormat::GREEN . $this->config->get("message_pos1_set"));
            $event->cancel();
        } elseif (isset($this->settingPos2[$playerName])) {
            $this->pos2 = $event->getBlock()->getPosition();
            unset($this->settingPos2[$playerName]);
            $player->sendMessage(TextFormat::GREEN . $this->config->get("message_pos2_set"));
            $event->cancel();
        }
    }
}
