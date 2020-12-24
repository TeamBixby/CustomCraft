<?php

declare(strict_types=1);

namespace TeamBixby\CustomCraft;

use pocketmine\inventory\FurnaceRecipe;
use pocketmine\inventory\ShapedRecipe;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

use function array_map;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
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
	}

	public function registerFurnaceRecipe(Item $input, Item $output) : void{
		$recipe = new FurnaceRecipe($input, $output);
		$this->getServer()->getCraftingManager()->registerFurnaceRecipe($recipe);
		$this->syncCraftingData();
	}

	private function syncCraftingData() : void{
		$this->getServer()->getCraftingManager()->buildCraftingDataCache();

		foreach($this->getServer()->getOnlinePlayers() as $player){
			$player->sendDataPacket($this->getServer()->getCraftingManager()->getCraftingDataPacket());
		}
	}
}