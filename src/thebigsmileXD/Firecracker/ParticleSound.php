<?php

namespace thebigsmileXD\Firecracker;

use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\Plugin;

class ParticleSound extends PluginTask{

	public function __construct(Plugin $owner){
		parent::__construct($owner);
		$this->plugin = $owner;
	}

	public function onRun($currentTick){
		$this->getOwner()->makeParticleSound();
	}

	public function cancel(){
		$this->getHandler()->cancel();
	}
}
?>