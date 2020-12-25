<?php

declare(strict_types=1);

namespace TeamBixby\CustomCraft;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\inventory\FurnaceRecipe;
use pocketmine\inventory\ShapedRecipe;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use TeamBixby\CustomCraft\command\ManageCraftCommand;

use function array_map;
use function array_merge;
use function array_values;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function json_decode;
use function json_encode;

class CustomCraft extends PluginBase{
	use SingletonTrait;

	protected $data = [
		"crafting" => [],
		"furnace" => []
	];

	public function onLoad() : void{
		self::setInstance($this);
	}

	public function onEnable() : void{
		if(file_exists($file = $this->getDataFolder() . "custom_craft_data.json")){
			$this->data = json_decode(file_get_contents($file), true);
		}
		$this->registerShapedRecipes();
		$this->registerFurnaceRecipes();

		$this->getServer()->getCraftingManager()->buildCraftingDataCache();

		$this->getServer()->getCommandMap()->register("mcc", new ManageCraftCommand());

		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
	}

	public function onDisable() : void{
		file_put_contents($this->getDataFolder() . "custom_craft_data.json", json_encode($this->data));
	}

	public function registerShapedRecipes() : void{
		$manager = $this->getServer()->getCraftingManager();
		foreach($this->data["crafting"] as $craftData){
			[$a, $b, $c, $d, $e, $f, $g, $h, $i] = array_map(function(array $data) : Item{
				return Item::jsonDeserialize($data);
			}, $craftData["input"]);
			$output = Item::jsonDeserialize($craftData["output"]);
			$recipe = new ShapedRecipe([
				"ABC",
				"DEF",
				"GHI"
			], [
				"A" => $a,
				"B" => $b,
				"C" => $c,
				"D" => $d,
				"E" => $e,
				"F" => $f,
				"G" => $g,
				"H" => $h,
				"I" => $i
			], [$output]);
			$manager->registerShapedRecipe($recipe);
		}
	}

	public function registerFurnaceRecipes() : void{
		$manager = $this->getServer()->getCraftingManager();
		foreach($this->data["furnace"] as $furnaceData){
			$input = Item::jsonDeserialize($furnaceData["input"]);
			$output = Item::jsonDeserialize($furnaceData["output"]);
			$recipe = new FurnaceRecipe($input, $output);
			$manager->registerFurnaceRecipe($recipe);
		}
	}

	public function registerShapedRecipe(array $items, Item $output) : void{
		$recipe = new ShapedRecipe(["ABC", "DEF", "GHI"], $items, [$output]);
		$this->getServer()->getCraftingManager()->registerShapedRecipe($recipe);
		$this->syncCraftingData();

		$this->data["crafting"][] = [
			"input" => array_map(function(Item $item) : array{
				return $item->jsonSerialize();
			}, array_values($items)),
			"output" => $output->jsonSerialize()
		];
	}

	public function registerFurnaceRecipe(Item $input, Item $output) : void{
		$recipe = new FurnaceRecipe($output, $input);
		$this->getServer()->getCraftingManager()->registerFurnaceRecipe($recipe);
		$this->syncCraftingData();

		$this->data["furnace"][] = ["input" => $input->jsonSerialize(), "output" => $output->jsonSerialize()];
	}

	private function syncCraftingData() : void{
		$this->getServer()->getCraftingManager()->buildCraftingDataCache();

		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->sendDataPacket($this->getServer()->getCraftingManager()->getCraftingDataPacket());
		}
	}

	public function sendAddCraftingMenu(Player $player) : void{
		$menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
		$menu->setName("Add crafting recipe");
		$menu->setListener(function(InvMenuTransaction $action) : InvMenuTransactionResult{
			$item = $action->getOut();
			$return = $action->discard();

			if($item->getNamedTagEntry("border") !== null){
				return $return;
			}
			return $action->continue();
		});
		$craftSlots = [19, 20, 21, 28, 29, 30, 37, 38, 39];
		$resultSlots = 33;
		$menu->setInventoryCloseListener(function(Player $player) use ($craftSlots, $resultSlots, $menu) : void{
			$allEmpty = true;
			foreach($craftSlots as $slot){
				if(!$menu->getInventory()->getItem($slot)->isNull()){
					$allEmpty = false;
					break;
				}
			}
			if($allEmpty){
				$player->sendMessage("You must provide the recipe.");
				return;
			}
			if($menu->getInventory()->getItem($resultSlots)->isNull()){
				$player->sendMessage("You must provide the result item.");
				return;
			}
			$a = "A";
			$res = [];
			foreach($craftSlots as $slot){
				$res[$a] = $menu->getInventory()->getItem($slot);
				$a++;
			}
			$this->registerShapedRecipe($res, $menu->getInventory()->getItem($resultSlots));
			$player->sendMessage("Success");
		});

		$border = ItemFactory::get(ItemIds::IRON_BARS);
		$border->setNamedTagEntry(new ByteTag("border", (int) true));
		$border->setCustomName("§l ");

		for($i = 0; $i < $menu->getInventory()->getSize(); $i++){
			if(!in_array($i, array_merge($craftSlots, [$resultSlots]))){
				$menu->getInventory()->setItem($i, $border);
			}
		}
		$menu->send($player);
	}

	public function sendAddFurnaceMenu(Player $player) : void{
		$inputSlot = 11;
		$outputSlot = 15;
		$borderSlot = 13;

		$menu = InvMenu::create(InvMenu::TYPE_CHEST);
		$menu->setName("Add furnace recipe");
		$menu->setListener(function(InvMenuTransaction $action) : InvMenuTransactionResult{
			$discard = $action->discard();
			$item = $action->getOut();

			if($item->getNamedTagEntry("border") !== null){
				return $discard;
			}

			return $action->continue();
		});
		$menu->setInventoryCloseListener(function(Player $player) use ($menu, $inputSlot, $outputSlot) : void{
			$input = $menu->getInventory()->getItem($inputSlot);
			$output = $menu->getInventory()->getItem($outputSlot);

			if($input->isNull() || $output->isNull()){
				$player->sendMessage("You must provide the input and output.");
				return;
			}
			$this->registerFurnaceRecipe($input, $output);
			$player->sendMessage("Success");
		});
		$border = ItemFactory::get(ItemIds::IRON_BARS);
		$border->setNamedTagEntry(new ByteTag("border", (int) true));
		$border->setCustomName("§l ");
		for($i = 0; $i < $menu->getInventory()->getSize(); $i++){
			if($i !== $inputSlot && $i !== $outputSlot && $i !== $borderSlot){
				$menu->getInventory()->setItem($i, $border);
			}
		}
		$border = ItemFactory::get(-161)->setCustomName("§l ");
		$border->setNamedTagEntry(new ByteTag("border", (int) true));
		$menu->getInventory()->setItem($borderSlot, $border);
		$menu->send($player);
	}
}