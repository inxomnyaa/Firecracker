<?php

namespace thebigsmileXD\Firecracker;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;
use pocketmine\Player;

class NewCracker extends PluginTask{

	public function __construct(Plugin $owner, Player $player){
		parent::__construct($owner);
		$this->plugin = $owner;
		$this->player = $player;
	}

	public function onRun($currentTick){
		$this->getOwner()->giveFirecracker($this->player);
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}
?>