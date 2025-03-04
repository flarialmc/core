<?php

declare(strict_types=1);

namespace LobbyCore;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use jojoe77777\FormAPI\SimpleForm;
use ItzLightyHD\KnockbackFFA\event\PlayerKillEvent;

class Main extends PluginBase implements Listener {

    private Config $friends;
    private Config $friendRequests;
    private Config $levels;

    public function onEnable(): void {
        $this->getLogger()->info("LobbyCore has been enabled!");

        @mkdir($this->getDataFolder());

        $this->levels = new Config($this->getDataFolder() . "levels.yml", Config::YAML);
        $this->friends = new Config($this->getDataFolder() . "friends.yml", Config::YAML);
        $this->friendRequests = new Config($this->getDataFolder() . "friendRequests.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function getLevel(Player $player): int {
        $name = $player->getName();
        $levelData = $this->levels->get($name, ["level" => 1, "kills" => 0]);
        return (int) $levelData["level"];
    }

    public function onPlayerKill(PlayerKillEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();
        $levelData = $this->levels->get($name, ["level" => 1, "kills" => 0]);
        
        $levelData["kills"]++;
        $requiredKills = $this->getRequiredKills($levelData["level"]);

        if ($levelData["kills"] >= $requiredKills) {
            $levelData["level"]++;
            $levelData["kills"] = 0;
            $player->sendMessage("§aYou have leveled up to Level " . $levelData["level"] . "!");
        }

        $this->levels->set($name, $levelData);
        $this->levels->save();
    }

    private function getRequiredKills(int $level): int {
        if ($level < 20) return 12;
        if ($level < 40) return 16;
        if ($level < 80) return 20;
        if ($level < 99) return 25;
        return 30;
    }

    public function openFriendMenu(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) return;
            
            switch ($data) {
                case 0:
                    $this->sendFriendRequestForm($player);
                    break;
                case 1:
                    $this->viewFriendRequests($player);
                    break;
            }
        });

        $form->setTitle("Friends Menu");
        $form->setContent("Select an option:");
        $form->addButton("Add Friend");
        $form->addButton("View Requests");
        
        $player->sendForm($form);
    }

    private function sendFriendRequestForm(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) return;
            
            $targetName = array_values($this->getServer()->getOnlinePlayers())[$data]->getName();
            $this->sendFriendRequest($player, $targetName);
        });
        
        $form->setTitle("Select a Player");
        foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer !== $player) {
                $form->addButton($onlinePlayer->getName());
            }
        }
        
        $player->sendForm($form);
    }

    private function sendFriendRequest(Player $player, string $targetName): void {
        $requests = $this->friendRequests->get($targetName, []);
        if (in_array($player->getName(), $requests)) {
            $player->sendMessage("§cYou have already sent a friend request to {$targetName}!");
            return;
        }
        $requests[] = $player->getName();
        $this->friendRequests->set($targetName, $requests);
        $this->friendRequests->save();
        $player->sendMessage("§aFriend request sent to {$targetName}!");
    }

    private function viewFriendRequests(Player $player): void {
        $name = $player->getName();
        $requests = $this->friendRequests->get($name, []);
        
        $form = new SimpleForm(function (Player $player, ?int $data) use ($requests) {
            if ($data === null) return;
            
            $targetName = $requests[$data];
            $this->acceptFriendRequest($player, $targetName);
        });
        
        $form->setTitle("Friend Requests");
        if (empty($requests)) {
            $form->setContent("No pending friend requests.");
        } else {
            foreach ($requests as $request) {
                $form->addButton($request);
            }
        }
        
        $player->sendForm($form);
    }

    private function acceptFriendRequest(Player $player, string $targetName): void {
        $name = $player->getName();
        $friends = $this->friends->get($name, []);
        $friends[] = $targetName;
        $this->friends->set($name, $friends);
        $this->friends->save();
        
        $requests = $this->friendRequests->get($name, []);
        $requests = array_diff($requests, [$targetName]);
        $this->friendRequests->set($name, $requests);
        $this->friendRequests->save();

        $player->sendMessage("§aYou are now friends with {$targetName}!");
    }
}
