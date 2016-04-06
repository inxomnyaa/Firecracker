<?php

namespace thebigsmileXD\Firecracker;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;
use pocketmine\entity\Item;

class ExplodeCracker extends PluginTask{

	public function __construct(Plugin $owner, Item $item, $id){
		parent::__construct($owner);
		$this->plugin = $owner;
		$this->item = $item;
		$this->id = $id;
	}

	public function onRun($currentTick){
		$this->getOwner()->explodeFirecracker($this->item, $this->id);
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}
?>