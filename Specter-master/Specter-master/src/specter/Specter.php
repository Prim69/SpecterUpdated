<?php

namespace specter;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\cheat\PlayerIllegalMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use specter\network\SpecterInterface;
use specter\network\SpecterPlayer;

class Specter extends PluginBase implements Listener{

	private $interface;

    public function onEnable(){
        $this->saveDefaultConfig();
        $this->interface = new SpecterInterface($this);
        $this->getServer()->getNetwork()->registerInterface($this->interface);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }


    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        if (isset($args[0])) {
            switch ($args[0]) {
                case 'spawn':
                    if (isset($args[1])) {
                        if ($this->getInterface()->openSession($args[1], "SEXY", 42069)) {
                            $sender->sendMessage("Session started.");
                        } else {
                            $sender->sendMessage("Failed to open session");
                        }
                        return true;
                    }
                    break;
                case 'close':
                    if (isset($args[1])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if ($player instanceof SpecterPlayer) {
                            $player->close("", "client disconnect.");
                        } else {
                            $sender->sendMessage("That player isn't managed by specter.");
                        }
                    } else {
                        $sender->sendMessage("Usage: /specter quit <p>");
                    }
                    break;
                case 'attack':
                case 'a':
                    if (isset($args[2])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if ($player instanceof SpecterPlayer) {
                            if (substr($args[2], 0, 4) === "eid:") {
                                $victimId = substr($args[2], 4);
                                if (!is_numeric($victimId)) {
                                    $sender->sendMessage("Usage: /specter attack <attacker> <victim>|<eid:<victim eid>>");
                                    return true;
                                }
                                if (!($victim = $player->getLevel()->getEntity($victimId) instanceof Entity)) {
                                    $sender->sendMessage("There is no entity with entity ID $victimId in {$player->getName()}'s level");
                                    return true;
                                }
                            } else {
                                $victim = $this->getServer()->getPlayer($args[2]);
                                if ($victim instanceof Player) {
                                    $victimId = $victim->getId();
                                } else {
                                    $sender->sendMessage("Player $args[2] not found");
                                    return true;
                                }
                            }
                            $damage = floatval($args[3] ?? 0.0);
                            $ev = new EntityDamageByEntityEvent($player, $victim, EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK, $damage, [], 0.0);
                            $victim->attack($ev);
                            $pk = new AnimatePacket();
                            $pk->entityRuntimeId = $player->getId();
                            $pk->action = AnimatePacket::ACTION_SWING_ARM;
                            $this->getInterface()->queueReply($pk, $player->getName());
                            $this->getLogger()->info(TextFormat::LIGHT_PURPLE . "{$player->getName()} attacking {$victim->getName()}(eid:{$victimId}) with {$damage} damage");
                        } else {
                            $sender->sendMessage("That player isn't managed by specter.");
                        }
                    } else {
                        $sender->sendMessage("Usage: /specter attack <attacker> [eid:]<victim> [damage]");
                    }
                    break;
                case 'chat':
                    if (isset($args[2])) {
                        $player = $this->getServer()->getPlayer($args[1]);
                        if ($player instanceof SpecterPlayer) {
                            $pk = new TextPacket();
                            $pk->type = TextPacket::TYPE_CHAT;
                            $pk->sourceName = "";
                            $pk->message = implode(" ", array_slice($args, 2));
                            $this->getInterface()->queueReply($pk, $player->getName());
                        } else {
                            $sender->sendMessage("That player isn't managed by specter.");
                        }
                    } else {
                        $sender->sendMessage("Usage: /specter chat <p> <data>");
                    }
                    break;
                case "respawn":
                case "r":
                    if (!isset($args[1])) {
                        $sender->sendMessage("Usage: /specter respawn <player>");
                        return true;
                    }
                    $player = $this->getServer()->getPlayer($args[1]);
                    if ($player instanceof SpecterPlayer) {
                        if (!$player->spec_needRespawn) {
                            $this->interface->queueReply(new RespawnPacket(), $player->getName());
                            $respawnPK = new PlayerActionPacket();
                            $respawnPK->action = PlayerActionPacket::ACTION_RESPAWN;
                            $respawnPK->entityRuntimeId = $player->getId();
                            $this->interface->queueReply($respawnPK, $player->getName());
                        } else {
                            $sender->sendMessage("{$player->getName()} doesn't need respawning.");
                        }
                    } else {
                        $sender->sendMessage("That player isn't a specter player");
                    }
                    break;
            }
        }
        return false;
    }

    /**
     * @priority HIGHEST
     * @param PlayerIllegalMoveEvent $event
     */
    public function onIllegalMove(PlayerIllegalMoveEvent $event)
    {
        if ($event->getPlayer() instanceof SpecterPlayer && $this->getConfig()->get('allowIllegalMoves')) {
            $event->setCancelled();
        }
    }
    /*
        /**
         * @priority MONITOR
         * @param DataPacketReceiveEvent $pk
         *
        public function onDataPacketRecieve(DataPacketReceiveEvent $pk){
            if($pk->getPacket() instanceof RequestChunkRadiusPacket){
                $this->getLogger()->info("RADIUS:" . $pk->getPacket()->radius);
            }
            $this->getLogger()->info("GOT:" . get_class($pk->getPacket()));
        }

        /**
         * @priority MONITOR
         * @param DataPacketSendEvent $pk
         *
        public function onDataPacketSend(DataPacketSendEvent $pk){
            if(!($pk->getPacket() instanceof SetTimePacket)) {
                $this->getLogger()->info("SEND:" . get_class($pk->getPacket()));
            }
        }
    */

    public function getInterface(){
        return $this->interface;
    }
	//doesnt work
    public function onDamage(EntityDamageByEntityEvent $event){
    	if($event->isCancelled()) return;
    	$player = $event->getEntity();
    	$damager = $event->getDamager();
    	if($player instanceof SpecterPlayer && $damager instanceof Player){
    		$player->knockBack($damager, 0, $player->x - $damager->x, $player->z - $damager->z, 0.4);
		}
	}

}
