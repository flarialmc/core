<?php

declare(strict_types=1);

namespace LobbyCore;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use ItzLightyHD\KnockbackFFA\event\PlayerKillEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\item\Item;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use pocketmine\entity\animal\walking\Wolf;
use pocketmine\entity\animal\walking\Cat;
use pocketmine\entity\animal\flying\Parrot;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\Task;

class Main extends PluginBase implements Listener {
    private ?FloatingTextParticle $floatingText = null;
    private Config $friends;
    private Config $friendRequests;
    private Config $levels;
    private array $pets = [];
    private array $petNames = [];
    private Position $leaderboardPosition;
    public function onEnable(): void {
        $this->getLogger()->info("LobbyCore has been enabled!");

        @mkdir($this->getDataFolder());
        
        $this->levels = new Config($this->getDataFolder() . "levels.yml", Config::YAML);
        $this->friends = new Config($this->getDataFolder() . "friends.yml", Config::YAML);
        $this->friendRequests = new Config($this->getDataFolder() . "friendRequests.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
        $this->updateLeaderboard();
    }), 20 * 10);
    }
   


    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $world = $player->getWorld();
        
        if ($world->getFolderName() === "lobby") { 
            $this->giveLobbyItems($player);
        }
      }
    private function giveLobbyItems(Player $player): void {
        $inventory = $player->getInventory();
        $inventory->clearAll();
        
        $gameSelector = VanillaItems::COMPASS()->setCustomName("§aGame Selector");
        $cosmeticsMenu = VanillaItems::TOTEM()->setCustomName("§bCosmetics");
        $friendsMenu = VanillaItems::BOOK()->setCustomName("§eFriends");
        $partyMenu = VanillaItems::CLOCK()->setCustomName("§6Party");

        $inventory->setItem(0, $gameSelector);
        $inventory->setItem(6, $cosmeticsMenu);
        $inventory->setItem(7, $friendsMenu);
        $inventory->setItem(8, $partyMenu);
    }

public function onPlayerItemUse(PlayerItemUseEvent $event): void {
    $player = $event->getPlayer();
    $item = $event->getItem();

    switch ($item->getCustomName()) {
        case "§aGame Selector":
            $this->openGameSelector($player);
            break;
        case "§bCosmetics":
            $this->openCosmeticsMenu($player);
            break;
        case "§eFriends":
            $this->openFriendMenu($player);
            break;
        case "§6Party":
            $player->sendMessage("§cParty menu is not functional yet!");
            break;
    }
  }
    private function openCosmeticsMenu(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) return;
            
            if ($data === 0) {
                $this->getServer()->dispatchCommand($player, "pets");
            }
        });
        
        $form->setTitle("Cosmetics Menu");
        $form->addButton("§bOpen Pets Menu");
        
        $player->sendForm($form);
    }
    private function openGameSelector(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) return;
            
            if ($data === 0) {
                $this->getServer()->dispatchCommand($player, "kbffa join");
            }
        });

    $form->setTitle("§aGame Selector");
    $form->addButton("§aKnock FFA", 0, "textures/items/stick.png");
    $player->sendForm($form);
}

    public function getLevel(Player $player): int {
        $name = $player->getName();
        $levelData = $this->levels->get($name, ["level" => 1, "kills" => 0]);
        return (int) $levelData["level"];
    }

public function updateLeaderboard(): void {
    $data = $this->levels->getAll();
    $leaderboard = [];
    
    foreach ($data as $name => $stats) {
        $leaderboard[$name] = $stats["kills"] ?? 0;
    }
    
    arsort($leaderboard); // Sort by kills in descending order
    $topPlayers = array_slice($leaderboard, 0, 10, true); // Get top 10
    
    $leaderboardText = "§l§6Top 10 Kill Leaders:\n";
    $position = 1;
    foreach ($topPlayers as $name => $kills) {
        $leaderboardText .= "§e#{$position} §f{$name}: §c{$kills} kills\n";
        $position++;
    }
    
    $position = new Position(7, 10, -54, $this->getServer()->getWorldManager()->getDefaultWorld()); // Change to your leaderboard location
    $this->spawnFloatingText($position, $leaderboardText);
}

private function spawnFloatingText(Position $position, string $text): void {
    $world = $position->getWorld();
    if ($world === null) {
        return;
    }

    // Check if an old FloatingTextParticle exists, and replace its text
    if ($this->floatingText !== null) {
        $this->floatingText->setText(""); // Make the old text invisible
    }

    // Create a new FloatingTextParticle with updated text
    $this->floatingText = new FloatingTextParticle($text);
    $world->addParticle(new Vector3($position->x, $position->y, $position->z), $this->floatingText);
}


public function onPlayerKill(PlayerKillEvent $event): void {
    $player = $event->getPlayer();
    $name = $player->getName();
    $levelData = $this->levels->get($name, ["level" => 1, "kills" => 0]);
    
    $levelData["kills"]++;
    $requiredKills = $this->getRequiredKills($levelData["level"]);

    if ($levelData["kills"] >= $requiredKills) {
        $levelData["level"]++;
        $player->sendMessage("§aYou have leveled up to Level " . $levelData["level"] . "!");
    }
    
    $this->levels->set($name, $levelData);
    $this->levels->save();
    $this->updateLeaderboard();
}

private function getRequiredKills(int $level): int {
    $totalKills = 0;
    for ($i = 1; $i <= $level; $i++) {
        if ($i < 20) {
            $totalKills += 12;
        } elseif ($i < 40) {
            $totalKills += 16;
        } elseif ($i < 80) {
            $totalKills += 20;
        } elseif ($i < 99) {
            $totalKills += 25;
        } else {
            $totalKills += 30;
        }
    }
    return $totalKills;
}


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game.");
            return true;
        }

        if ($command->getName() === "friend") {
            $this->openFriendMenu($sender);
        }
        return true;
    }

    private function openFriendMenu(Player $player): void {
        $form = new SimpleForm(function (Player $player, ?int $data) {
            if ($data === null) return;
            
            switch ($data) {
                case 0:
                    $this->sendFriendRequestForm($player);
                    break;
                case 1:
                    $this->viewFriendRequests($player);
                    break;
                case 2:
                    $this->viewFriendsList($player);
                    break;
            }
        });

        $form->setTitle("Friends Menu");
        $form->addButton("Add Friend");
        $form->addButton("View Requests");
        $form->addButton("View Friends List");
        
        $player->sendForm($form);
    }

    private function sendFriendRequestForm(Player $player): void {
        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data === null || empty($data[0])) return;
            $this->sendFriendRequest($player, $data[0]);
        });
        
        $form->setTitle("Add Friend");
        $form->addInput("Enter the player's username:");
        
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
            if ($data === null || !isset($requests[$data])) return;
            
            $this->handleFriendRequestResponse($player, $requests[$data]);
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

    private function handleFriendRequestResponse(Player $player, string $targetName): void {
        $form = new SimpleForm(function (Player $player, ?int $data) use ($targetName) {
            if ($data === null) return;
            
            if ($data === 0) {
                $this->acceptFriendRequest($player, $targetName);
            } else {
                $this->declineFriendRequest($player, $targetName);
            }
        });

        $form->setTitle("Friend Request from {$targetName}");
        $form->addButton("Accept");
        $form->addButton("Decline");

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

    private function declineFriendRequest(Player $player, string $targetName): void {
        $name = $player->getName();
        $requests = $this->friendRequests->get($name, []);
        $requests = array_diff($requests, [$targetName]);
        $this->friendRequests->set($name, $requests);
        $this->friendRequests->save();

        $player->sendMessage("§cYou declined the friend request from {$targetName}.");
    }

    private function viewFriendsList(Player $player): void {
        $name = $player->getName();
        $friends = $this->friends->get($name, []);
        
        $form = new SimpleForm(function (Player $player, ?int $data) {});
        $form->setTitle("Your Friends");
        if (empty($friends)) {
            $form->setContent("You have no friends added.");
        } else {
            foreach ($friends as $friend) {
                $form->addButton($friend);
            }
        }
        $player->sendForm($form);
    }
}
