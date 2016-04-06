<?php

/*
 * Firecracker
 * A plugin by thebigsmileXD
 * http://github.com/ImagicalCorp/Firecracker
 * Happy new year!
 */
namespace thebigsmileXD\Firecracker;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\entity\Item as ItemEntity;
use pocketmine\network\protocol\ExplodePacket;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Server;
use pocketmine\network\Network;
use pocketmine\math\Vector3;
use pocketmine\level\sound\FizzSound;
use pocketmine\level\particle\FlameParticle;
use pocketmine\event\player\PlayerItemHeldEvent;

class Main extends PluginBase implements Listener{
	public $crackers = array();
	public $givetask = array();
	public $particlesoundtask = null;

	public function onLoad(){
		$this->getLogger()->info(TextFormat::GREEN . "Loading " . $this->getDescription()->getFullName());
	}

	public function onEnable(){
		$this->makeSaveFiles();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::GREEN . "Enabling " . $this->getDescription()->getFullName() . " by " . $this->getDescription()->getAuthors()[0]);
		$this->getLogger()->info(TextFormat::GOLD . "Happy new Year! Have fun blasting everything up!");
	}

	private function makeSaveFiles(){
		$this->saveDefaultConfig();
		if(!$this->getConfig()->exists("give-items-after")){
			$this->getConfig()->set("give-items-after", 30);
		}
		if(!$this->getConfig()->exists("translation")){
			$this->getConfig()->set("translation", "Firecracker");
		}
		$this->setConfig();
	}

	public function setConfig(){
		$this->getConfig()->save();
		$this->getConfig()->reload();
	}

	public function onDisable(){
		$this->getLogger()->info(TextFormat::RED . "Disabling " . $this->getDescription()->getFullName() . " by " . $this->getDescription()->getAuthors()[0]);
	}

	public function runIngame($sender){
		if($sender instanceof Player) return true;
		else{
			$sender->sendMessage(TextFormat::RED . "Run this command ingame");
			return false;
		}
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "firecracker":
				{
					$command = strtolower($command);
					if(count($args) > 0){
						switch($args[0]){
							case "get":
								{
									if($this->runIngame($sender)){
										$this->giveFirecracker($sender);
										return true;
									}
								}
							default:
								return false;
						}
					}
					else
						return false;
				}
			default:
				return false;
		}
	}

	public function giveFirecracker(Player $player){
		if($player->isOnline()){
			$player->getInventory()->remove(Item::get(Item::BRICK));
			$player->getInventory()->addItem(Item::get(Item::BRICK, 0, 1));
		}
		if(isset($this->givetask[$player->getName()])){
			$this->getServer()->getScheduler()->cancelTask($this->givetask[$player->getName()]);
			unset($this->givetask[$player->getName()]);
		}
	}

	public function explodeFirecracker(ItemEntity $itementity, $id){
		$pk = new ExplodePacket();
		$pk->x = $itementity->x;
		$pk->y = $itementity->y;
		$pk->z = $itementity->z;
		$pk->radius = 10;
		$pk->records = [new Vector3($itementity->x, $itementity->y + 0.5, $itementity->z)];
		Server::broadcastPacket($itementity->getLevel()->getChunkPlayers($itementity->x >> 4, $itementity->z >> 4), $pk->setChannel(Network::CHANNEL_BLOCKS));
		$itementity->kill();
		if(isset($this->crackers[$id])){
			$this->getServer()->getScheduler()->cancelTask($this->crackers[$id]);
			unset($this->crackers[$id]);
		}
		$count = 0;
		foreach($this->getServer()->getLevels() as $level){
			foreach($level->getEntities() as $entity){
				if($entity instanceof ItemEntity && $entity->getItem()->getId() === Item::BRICK){
					$count++;
				}
				if($count === 0){
					$this->getServer()->getScheduler()->cancelTask($this->particlesoundtask);
					$this->particlesoundtask = null;
				}
			}
		}
	}

	public function makeParticleSound(){
		foreach($this->getServer()->getLevels() as $level){
			foreach($level->getEntities() as $entity){
				if($entity instanceof ItemEntity && $entity->getItem()->getId() === Item::BRICK){
					$entity->getLevel()->addSound(new FizzSound($entity->getPosition()));
					$entity->getLevel()->addParticle(new FlameParticle(new Vector3($entity->x, $entity->y + 0.5, $entity->z)));
				}
			}
		}
	}

	public function onSelect(PlayerItemHeldEvent $event){
		if($event->getItem()->getId() === Item::BRICK){
			$event->getPlayer()->sendPopup(TextFormat::RED . ($this->getConfig()->get("translation") !== false?$this->getConfig()->get("translation"):"Firecracker"));
		}
	}

	public function onDrop(PlayerDropItemEvent $event){
		$item = $event->getItem();
		if($item->getId() === Item::BRICK){
			$player = $event->getPlayer();
			if(!isset($this->givetask[$player->getName()])) $this->givetask[$player->getName()] = $this->getServer()->getScheduler()->scheduleDelayedTask(new NewCracker($this, $player), $this->getConfig()->get("give-items-after") * 20)->getTaskId();
		}
	}

	public function onItemSpawn(ItemSpawnEvent $event){
		$entityitem = $event->getEntity();
		$itemitem = $entityitem->getItem();
		if($itemitem->getId() === Item::BRICK){
			$this->crackers[count($this->crackers)] = $this->getServer()->getScheduler()->scheduleDelayedTask(new ExplodeCracker($this, $entityitem, count($this->crackers)), 60)->getTaskId();
			if(count($this->crackers) > 0){
				if($this->particlesoundtask === null) $this->particlesoundtask = $this->getServer()->getScheduler()->scheduleRepeatingTask(new ParticleSound($this), 10)->getTaskId();
			}
			$entityitem->setPickupDelay(300);
			$entityitem->setNameTagVisible(true);
			$entityitem->setNameTag(TextFormat::RED . ($this->getConfig()->get("translation") !== false?$this->getConfig()->get("translation"):"Firecracker"));
		}
	}

	public function onJoin(PlayerJoinEvent $event){
		if(!isset($this->givetask[$event->getPlayer()->getName()])) $this->givetask[$event->getPlayer()->getName()] = $this->getServer()->getScheduler()->scheduleDelayedTask(new NewCracker($this, $event->getPlayer()), 20)->getTaskId();
	}
}