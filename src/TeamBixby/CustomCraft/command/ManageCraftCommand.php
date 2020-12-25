<?php

declare(strict_types=1);

namespace TeamBixby\CustomCraft\command;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use TeamBixby\CustomCraft\CustomCraft;

class ManageCraftCommand extends PluginCommand{

	public function __construct(){
		parent::__construct("mcc", CustomCraft::getInstance());
		$this->setDescription("Manage custom craft");
		$this->setPermission("customcraft.command");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return false;
		}
		if(!$sender instanceof Player){
			$sender->sendMessage("Please use this command as a player!");
			return false;
		}
		switch($args[0] ?? "x"){
			case "addcraft":
				CustomCraft::getInstance()->sendAddCraftingMenu($sender);
				break;
			case "addfurnace":
				CustomCraft::getInstance()->sendAddFurnaceMenu($sender);
				break;
			default:
				$sender->sendMessage("Usage: /mcc [addcraft|addfurnace]");
		}
		return true;
	}
}