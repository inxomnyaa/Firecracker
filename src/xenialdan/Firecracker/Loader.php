<?php

/*
 * Firecracker
 * A plugin by XenialDan
 * http://github.com/thebigsmileXD/Firecracker
 * Happy new year!
 */

namespace XenialDan\Firecracker;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\level\particle\ExplodeParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\level\sound\FizzSound;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;

class Loader extends PluginBase implements Listener
{
    /** @var Item */
    public static $brick;

    public function onEnable()
    {
        $this->makeSaveFiles();
        $item = ItemFactory::get(ItemIds::BRICK);
        $item->setCustomName($this->getConfig()->get("translation", "Firecracker"));
        $item->setLore(["Drop me and i will explode!"]);
        self::$brick = $item;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        #$this->getLogger()->info(TextFormat::GOLD . "Happy new Year! Be safe!");
        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(function (int $currentTick): void {
            $this->makeParticleSound();
        }), 20, 20);
    }

    private function makeSaveFiles(): void
    {
        $this->saveDefaultConfig();
        if (!$this->getConfig()->exists("give-items-after")) {
            $this->getConfig()->set("give-items-after", 30);
        }
        if (!$this->getConfig()->exists("translation")) {
            $this->getConfig()->set("translation", "Firecracker");
        }
        $this->setConfig();
    }

    public function setConfig(): void
    {
        $this->getConfig()->save();
    }

    public function runIngame(CommandSender $sender): bool
    {
        if ($sender instanceof Player) return true;
        else {
            $sender->sendMessage(TextFormat::RED . "Run this command ingame");
            return false;
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "firecracker":
            {
                if (count($args) > 0) {
                    switch ($args[0]) {
                        case "get":
                        {
                            if ($this->runIngame($sender)) {
                                $this->giveFirecracker($sender);
                                return true;
                            }
                        }
                        default:
                            return false;
                    }
                } else
                    return false;
            }
            default:
                return false;
        }
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($player) : void {
            $this->giveFirecracker($player);
        }), 20);
    }

    public function onDrop(PlayerDropItemEvent $event): void
    {
        $item = $event->getItem();
        if ($item->equals(self::$brick)) {
            $player = $event->getPlayer();
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($player): void {
                $this->giveFirecracker($player);
            }), $this->getConfig()->get("give-items-after", 30) * 20);
        }
    }

    public function onItemSpawn(ItemSpawnEvent $event): void
    {
        $entityitem = $event->getEntity();
        $itemitem = $entityitem->getItem();
        if ($itemitem->equals(self::$brick)) {
            $entityitem->setPickupDelay(300);
            $entityitem->setNameTagVisible(true);
            $entityitem->setNameTag(TextFormat::RED . $itemitem->getCustomName());
        }
    }

    public function giveFirecracker(Player $player): void
    {
        if ($player->isOnline()) {
            if (!$player->getInventory()->contains(self::$brick)) {
                $player->getInventory()->addItem(self::$brick);
                $player->sendWhisper($this->getDescription()->getPrefix(), "You got a new firecracker!");
            }
        }
    }

    public function makeParticleSound(): void
    {
        foreach ($this->getServer()->getLevels() as $level) {
            if ($level->isClosed() || count($level->getPlayers()) <= 0) continue;
            foreach ($level->getEntities() as $entity) {
                if (!$entity instanceof ItemEntity) continue;
                if ($entity->getItem()->equals(self::$brick)) {
                    if ($entity->ticksLived >= 60) {
                        $this->explodeFirecracker($entity);
                        continue;
                    }
                    $entity->getLevel()->addSound(new FizzSound($entity->asVector3()));
                    $entity->getLevel()->addParticle(new FlameParticle($entity->add(0, 0.5)));
                }
            }
        }
    }

    public function explodeFirecracker(ItemEntity $itementity): void
    {
        if (!$itementity->isClosed() && $itementity->isValid()) {
            $itementity->getLevel()->addParticle(new ExplodeParticle($itementity->asVector3()));
            $itementity->getLevel()->broadcastLevelSoundEvent($itementity->asVector3(), LevelSoundEventPacket::SOUND_EXPLODE);
            $itementity->flagForDespawn();
        }
    }
}